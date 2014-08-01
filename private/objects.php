<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_audio_objects($ciniki) {
	$objects = array();
	$objects['file'] = array(
		'name'=>'Audio File',
		'sync'=>'yes',
		'backup'=>'no',
		'table'=>'ciniki_audio',
		'fields'=>array(
			'parent_id'=>array('ref'=>'ciniki.audio.file'),
			'type'=>array(),
			'original_filename'=>array(),
			'title'=>array(),
			'checksum'=>array(),
			),
		'history_table'=>'ciniki_audio_history',
		);
	
	return array('stat'=>'ok', 'objects'=>$objects);
}
?>
