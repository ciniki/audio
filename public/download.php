<?php
//
// Description
// ===========
// This function will add a new audio into the audio database.  The audio
// data should be posted as a file upload.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant to add the audio to.
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_audio_download(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'audio_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Audio File'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'audio', 'private', 'checkAccess');
    $rc = ciniki_audio_checkAccess($ciniki, $args['tnid'], 'ciniki.audio.download'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // Get the uuid for the file
    //
    $strsql = "SELECT ciniki_tenants.uuid AS tenant_uuid, ciniki_audio.uuid AS file_uuid, "
        . "ciniki_audio.title AS name, ciniki_audio.type, ciniki_audio.original_filename "
        . "FROM ciniki_audio, ciniki_tenants "
        . "WHERE ciniki_audio.id = '" . ciniki_core_dbQuote($ciniki, $args['audio_id']) . "' "
        . "AND ciniki_audio.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ciniki_tenants.id = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.audio', 'file');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['file']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.33', 'msg'=>'Unable to find file'));
    }
    $filename = $rc['file']['original_filename'];
    $file_uuid = $rc['file']['file_uuid'];
    $tenant_uuid = $rc['file']['tenant_uuid'];
    $file_type = $rc['file']['type'];

    //
    // Move the file into storage
    //
    $storage_dirname = $ciniki['config']['ciniki.core']['storage_dir'] . '/'
        . $tenant_uuid[0] . '/' . $tenant_uuid 
        . '/ciniki.audio/'
        . $file_uuid[0];
    $storage_filename = $storage_dirname . '/' . $file_uuid;
    if( !is_file($storage_filename) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.34', 'msg'=>'Unable to find file'));
    }

    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
    header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT"); 
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    // Set mime header
    switch($file_type) {
        case 20: header('Content-Type: audio/ogg'); break;
        case 30: header('Content-Type: audio/x-wav'); break;
        case 40: header('Content-Type: audio/mpeg'); break;
    }
    // Specify Filename
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Content-Length: ' . filesize($storage_filename));
    header('Cache-Control: max-age=0');

    $fp = fopen($storage_filename, 'rb');
    fpassthru($fp);

    return array('stat'=>'exit');
}
?>
