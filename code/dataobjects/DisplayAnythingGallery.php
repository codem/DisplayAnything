<?php
/**
 * Contains Gallery dataobjects and supporting classes
 */

/**
 * Gallery usage configuration class
 */
class DisplayAnythingGalleryUsage extends DataObject {

	static $db = array(
		'Title' => 'varchar(255)',
		'MimeTypes' => 'text',
	);
	
	public function TitleMap() {
		return $this->Title . " (" . $this->MimeTypes . ")";
	}
	
	protected function defaultUsageRecords() {
		return array(
			array(
				'Title' => 'Image',
				'MimeTypes' => 'image/png,image/jpg,image/jpeg,image/gif',
			),
			array(
				'Title' => 'Documents',
				'MimeTypes' => 'text/plain',
			),
		);
	}
	
	public static $summary_fields = array(
		'Title' => 'Usage',
		'MimeTypes' => 'Allowed MimeTypes',
	);
	
	
	/**
	 * requireDefaultRecords()
	 * @note seeds the usage table with some default 'gallery' types
	 * @todo if there is a less hackish way to do this I'm all ears
	 */
	public function requireDefaultRecords() {
		try {
			$query = "SELECT COUNT(ID) AS Records FROM DisplayAnythingGalleryUsage";
			if(($result = DB::Query($query)) && ($record = $result->current()) && ($record['Records'] == 0)) {
				$defaults = self::defaultUsageRecords();
				foreach($defaults as $default) {
					$usage = new DisplayAnythingGalleryUsage($default);
					$usage->write();
				}
				return TRUE;
			}
		} catch (Exception $e) {
		}
		return FALSE;
	}
}

/**
  * DisplayAnythingGallery()
  * @note contains many DisplayAnythingFile()s
 */
class DisplayAnythingGallery extends DataObject {
	static $db = array(
		'Title' => 'varchar(255)',
		'Description' => 'text',
		'Visible' => 'boolean',
		'Migrated' => 'boolean',//this is set when the gallery migration is complete
		//options for this gallery
		'ExtraMimeTypes' => 'text',//list of extra mimetypes for this gallery
		//In the future other config items for the gallery can go here
	);
	
	static $has_one = array(
		'Usage' => 'DisplayAnythingGalleryUsage',
	);
	
	static $has_many = array(
		'GalleryItems' => 'DisplayAnythingFile'
	);
	
	static $defaults = array(
		'Visible' => 1,
		'Migrated' => 0,
	);
	
	/**
	 * OrderedGalleryItems()
	 * @note return gallery items ordered as set in admin
	 */
	public function OrderedGalleryItems() {
		if($this->Visible == 1) {
			return DataObject::get('DisplayAnythingFile','GalleryID=' . $this->ID . ' AND Visible = 1', '`File`.`Sort` ASC, `File`.`Created` DESC');
		}
		return FALSE;
	}
}

/**
 * DisplayAnythingYouTubeGallery()
 * @note a gallery of DisplayAnythingYouTubeVideoFile(s)
 */
class DisplayAnythingYouTubeGallery extends DisplayAnythingGallery {
	static $has_many = array(
		'GalleryItems' => 'DisplayAnythingYouTubeVideoFile'
	);
	
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		$this->ClassName = __CLASS__;
	}
}
?>