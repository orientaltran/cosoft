<?php 
    $userId = $_GET['user'];
    $studyId = $_GET['study'];
    if (file_exists('reports/'. $userId . '/' . $studyId . '.html')) {
        $content = file_get_contents('reports/' . $userId . '/' . $studyId . '.html');
        echo $content;
    } else {
        echo 'Not found file';
    }
?>