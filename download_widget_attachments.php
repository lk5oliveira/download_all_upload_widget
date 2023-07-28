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
 * @param array $submissions_id the array returned by getFormSubmissions method Jotform API.
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
 * Download the files and save them in folders.
 * @param array $links list of links to download the files.
 * @return void downloads the files and stores them in folders.
 */
function zipDownloadFiles(array $links): void {
    global $formId;

    $zipFileName = "$formId.zip";
    $zip = new ZipArchive();

    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        foreach ($links as $submissionID => $submissionLinks) {
            $submissionDir = $submissionID . '/';
            $n = 0;
            foreach ($submissionLinks as $link) {
                $filename = "file$n.jpg";

                $ch = curl_init($link);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Set the maximum number of redirects to follow
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $fileContent = curl_exec($ch);

                // Check if file download was successful (HTTP status code 200)
                $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpStatus !== 200) {
                    echo "Error downloading file: $link - HTTP Status: $httpStatus" . PHP_EOL;
                } else {
                    // Add the file to the zip archive with the original filename and folder structure
                    $zip->addFromString($submissionDir . $filename, $fileContent);
                    echo "File '$filename' added to the zip archive." . PHP_EOL;
                }

                curl_close($ch);

                $n++;
            }
        }

        $zip->close();
        echo "Files zipped successfully." . PHP_EOL;
    } else {
        echo "Error creating zip file." . PHP_EOL;
    }
}

$linksToDownload = get_links($submissions);
zipDownloadFiles($linksToDownload);
