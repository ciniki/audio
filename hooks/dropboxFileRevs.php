<?php
//
// Description
// -----------
// This function returns the list of paths and revisions for files.
//
// Arguments
// ---------
// ciniki:
// business_id:     The ID of the business the photo is attached to.
//
// 
// Returns
// -------
// The audio ID that was added.
//
function ciniki_audio_hooks_dropboxFileRevs(&$ciniki, $business_id, $args) {
   
    $strsql = "SELECT original_filename, dropbox_path, dropbox_rev "
        . "FROM ciniki_audio "
        . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND dropbox_path = '" . ciniki_core_dbQuote($ciniki, $args['path']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.audio', array(
        array('container'=>'files', 'fname'=>'original_filename',
            'fields'=>array('dropbox_path', 'dropbox_rev')),
        ));
    return $rc;
}
?>
