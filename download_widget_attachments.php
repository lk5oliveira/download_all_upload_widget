<?php

/**
 * WARNING: This code is for workaround use and should not be used in a production environment.
 * 
 * This code does not include proper error handling or security measures and may expose vulnerabilities.
 * 
 * Use at your own risk and only in a controlled and isolated testing environment.
 * 
 * cURL SSL is disabled.
 * 
 * Delete the used API key after executing the code.
 * 
 * Author: Lucas Oliveira (lucasoliveiraso23@gmail.com)
 * Date: 2023-07-27
 */

include "JotForm.php";

// Declaring variables
$jotformAPI = new JotForm("YOUR_API_KEY");
$formId = 'FORM_ID';

// Get form submissions
$submissions = $jotformAPI->getFormSubmissions($formId);

/**
 * To get the links from the submissions ID.
 * @param array $submissions The array returned by getFormSubmissions method Jotform API.
 * @return array List of links to download.
 * // OUTPUT: array(submission_ID => array([0] => link))
 */
function get_links(array $submissions): array {
    $linksToDownload = array();

    foreach ($submissions as $answers) {
        if (!isset($answers['answers'])) {
            echo "answer not set";
        }
        foreach ($answers['answers'] as $answer) {
            if (isset($answer['cfname']) && isset($answer['answer']) && $answer['cfname'] == 'Image Upload Preview') {
                $jsonData = $answer['answer'];
                // Decode JSON data into an associative array
                $data = json_decode($jsonData, true);

                // Access the URL element
                if (isset($data['widget_metadata']['value'][0]['url'])) {
                    $url = $data['widget_metadata']['value'][0]['url'];
                    $linksToDownload[$answers['id']][] = "https://www.jotform.com/" . $url;
                } else {
                    echo "'$answers': URL element not found in the JSON data.";
                }
            }
        }
    }

    return $linksToDownload;
}

/**
 * Download the files and save them in folders inside the zip file.
 * @param array $submissions The array returned by getFormSubmissions method Jotform API.
 * @return void Downloads the files and stores them in folders inside the zip file.
 */
function downloadFiles(array $submissions): void {
    global $formId;

    foreach ($submissions as $submissionID => $submissionLinks) {
        $submissionDir = $formId . '/' . $submissionID . '/';
        if (!is_dir($submissionDir)) {
            mkdir($submissionDir, 0777, true);
        }

        $n = 0;
        foreach ($submissionLinks as $link) {
            $filename = "file$n.jpg";

            $ch = curl_init($link);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Set the maximum number of redirects to follow
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096); // Set a smaller chunk size

            $fileHandle = fopen($submissionDir . $filename, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);

            $result = curl_exec($ch);

            // Check if file download was successful (HTTP status code 200)
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpStatus !== 200) {
                echo "Error downloading file: $link - HTTP Status: $httpStatus" . PHP_EOL;
            } else {
                echo "File '$filename' downloaded to folder '$submissionDir' successfully." . PHP_EOL;
            }

            curl_close($ch);
            fclose($fileHandle);

            $n++;
            // Free up memory after each file is processed
            $result = null;
            $filename = null;
            gc_collect_cycles(); // Perform garbage collection to release any unused memory
        }
    }

    echo "Files downloaded successfully." . PHP_EOL;
}

/**
 * Zip the folder with the downloaded files and delete the folder and files from the root
 * This function was created to optmize the memory usage
 * @param string $formId
 * @return void zip the files and folders and delete the files and folders from the root.
 */
function zipAndDeleteFolders(string $formId): void {
    $zipFileName = "$formId.zip";
    $zip = new ZipArchive();

    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $directoryIterator = new RecursiveDirectoryIterator($formId);
        $files = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $niddlePosition = strpos($filePath, $formId);
                $relativePath = substr($filePath, $niddlePosition);
                $zip->addFile($filePath, $relativePath);
                echo $filePath . PHP_EOL;
            }
        }

        $zip->close();
        echo "Folders zipped successfully." . PHP_EOL;

        // Delete the original folders
        $iterator = new RecursiveDirectoryIterator($formId, RecursiveDirectoryIterator::SKIP_DOTS);
        $filesToDelete = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($filesToDelete as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($formId);
        echo "Original folders deleted." . PHP_EOL;
    } else {
        echo "Error creating zip file." . PHP_EOL;
    }
}


$links = get_links($submissions);

// Download the files to the root
downloadFiles($links);

// Zip the folders and delete it from the root
zipAndDeleteFolders($formId);

