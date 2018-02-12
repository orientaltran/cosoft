<?php
/**
 * GET findLines
 * Summary: 
 * Notes: Get a list of line
 * Output-Formats: [application/json]
 */
$router->GET('/api/v1/lines/{id}/getListLine', 'Api1\\Lines@loadPipeline');

/**
 * PUT savePipeline
 * Summary: 
 * Notes: 
 * Output-Formats: [application/json]
 */
$router->POST('/api/v1/lines/saveLines', 'Api1\\Lines@savePipelines');
/**
 * PUT savePDF
 * Summary: 
 * Notes: 
 * Output-Formats: [application/json]
 */
$router->POST('/api/v1/reports/savePDFs', 'Api1\\Reports@savePDF
s');