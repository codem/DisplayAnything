<?php
/**
 * Car
 * @note a simple dataobject used to illustrate gallery association with a DataObject, can do this with a DataObjectDecorator as well!
 */
class Car extends DataObject {
	public $db = array(
		'Title' => 'Varchar(255)',
		'Make' => 'Varchar(255)',
		'Colour' => 'Varchar(255)',
		'Doors' => 'Int',
		'IsBomb' => 'Boolean',
		'Model' => 'Varchar(255)',
	);
	
	public static $has_one = array(
		'Photos' => 'DisplayAnythingGallery',
	);
	
	public function getCmsFields() {
		$fields = parent::getCmsFields();

		//GALLERY per page
		$gallery = new DisplayAnythingGalleryField(
			$this,
			'Photos',
			'DisplayAnythingGallery'
		);
		$gallery->SetTargetLocation('/car_photos');//relative to ASSETS_PATH
		$fields->addFieldToTab('Root.Photos', $gallery);
		
		return $fields;
	}
}
?>