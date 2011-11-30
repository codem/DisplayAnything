<?php
/**
 * UploadAnythingFile()
 * @note our file class, extends File and provides extra bits and bobs
 */
class UploadAnythingFile extends File {

	private $meta;

	static $db = array(
		'Visible' => 'Boolean',
		'Caption' => 'Varchar(255)',
		'Description' => 'Text',//separate to File.Content
		//links to other content and calls to action
		'ExternalURL' => 'varchar(255)',
		'CallToActionText' => 'varchar(32)',
		'AlternateURL' => 'varchar(255)',//not in use
	);
	
	static $has_one = array(
		'InternalLink' => 'Page',
	);
	
	public function LinkToURL() {
		if(!empty($this->ExternalURL)) {
			return $this->ExternalURL;
		} else {
			$link = $this->InternalLink()->Link();
			if(!empty($link)) {
				return $link;
			}
		}
		return FALSE;
	}
	
	/**
	 * Event handler called before deleting from the database.
	 * @note we test for exceptions here and ignore them.. allowing the record to be deleted.
	 * @note if the file has been remove from the file system updateFileSystem will throw an exception
	 */
	protected function onBeforeDelete() {
		try {
			parent::onBeforeDelete();
		} catch (Exception $e) {}
	}
	
	static public function MimeType($location = "") {
		$mimeType = FALSE;
		if(!is_readable($location)) {
			return FALSE;
		}
		if(function_exists('finfo_open')) {
			//use finfo
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $location);
		} else {
			//maybe it's an image..
			$parts = @getimagesize($location);
			if(!empty($parts[2])) {
				$mimeType = image_type_to_mime_type($parts[2]);
			} else if(function_exists('mime_content_type')) {
				$mimeType = mime_content_type($location);
			}
		}
		return $mimeType;
	}
	
	public function IsImage($location = "") {
		if($location == "") {
			$location = $this->getFullPath();
		}
		$parts = @getimagesize($location);
		if(!empty($parts[2])) {
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * GetMeta()
	 * @note gets meta for current image. If it already exist, return that
	 */
	public function GetMeta() {
	
		if($this->meta) {
			return $this->meta;
		}
	
		$path = $this->getFullPath();
		$this->meta = array();
		$this->meta['width'] = '';
		$this->meta['height'] = '';
		$this->meta['size'] = $this->getSize();
		$this->meta['mimetype'] = self::MimeType($path);
		if($this->IsImage()) {
			$data = getimagesize($path);
			$this->meta['width'] = $data[0];
			$this->meta['height'] = $data[1];
		}
		return $this->meta;
	}
	
	public function Thumbnail($method,$width) {
		return $this->SetWidth($width);
	}
	
	public function PaddedImage($width, $height) {
		$is_image = $this->IsImage();
		if($is_image) {
			$image = new Image(
				array(
					'ID' => $this->ID,
					'Filename' => $this->Filename,
					'Name' => $this->Name,
					'ClassName' => 'Image',
					'Title' => $this->Title,
				)
			);
			$resize = $image->PaddedImage($width, $height);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function SetWidth($width) {
		$is_image = $this->IsImage();
		if($is_image) {
			$meta = $this->GetMeta();
			if($meta['width'] < $width) {
				$width = $meta['width'];
			}
			$image = new Image(
				array(
					'ID' => $this->ID,
					'Filename' => $this->Filename,
					'Name' => $this->Name,
					'ClassName' => 'Image',
					'Title' => $this->Title,
				)
			);
			$resize = $image->SetWidth($width);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function CroppedImage($width, $height) {
		$is_image = $this->IsImage();
		if($is_image) {
			$image = new Image(
				array(
					'ID' => $this->ID,
					'Filename' => $this->Filename,
					'Name' => $this->Name,
					'ClassName' => 'Image',
					'Title' => $this->Title,
				)
			);
			$resize = $image->CroppedImage($width, $height);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function OriginalURL() {
		return  $this->getURL();
	}
	
	public function LinkToText() {
		if($this->CallToActionText != '') {
			return $this->CallToActionText;
		} else if($this->Caption != '') {
			return $this->Caption;
		} else {
			return $this->Title;
		}
	}
	
	public function getCMSFields() {
		
		$fields = parent::getCMSFields();
		
		$fields->addFieldsToTab(
			'Root.FileInformation',
			array(
				new TextField('Title', 'Title of File', $this->Title),
				new TextField('CallToActionText', 'Call To Action Text (placed on button or link selected)', $this->CallToActionText),
				new TreeDropdownField(
						"InternalLinkID",
						"Internal page link",
						"SiteTree"
				),
				new TextField('ExternalURL', 'External link (e.g http://example.com/landing/page) - will override Internal Page Link', $this->ExternalURL),
				new TextField('Caption', 'File Caption', $this->Caption),
				new TextareaField('Description', 'File Description', 5, NULL, $this->Description),
			)
		);
		
		$meta = $this->GetMeta();
		
		$fields->addFieldsToTab(
			'Root.FilePreview',
			array(
				new LiteralField(
					'FileMetaData',
<<<HTML
<table class="uploadanythingfile_meta">
	<tbody>
		<tr><th>Size</th><td>{$meta['size']}</td></tr>
		<tr><th>Width</th><td>{$meta['width']}</td></tr>
		<tr><th>Height</th><td>{$meta['height']}</td></tr>
		<tr><th>Type</th><td>{$meta['mimetype']}</td></tr>
	</tbody>
</table>
HTML
				),
				new LiteralField(
					'FilePreviewLiteral',
					$this->Thumbnail('SetWidth', 400)
				)
			)
		);
		
		$fields->addFieldsToTab(
			'Root.Ownership',
			array(
				new DropDownField('OwnerID','File Owner', DataObject::get('Member')->map('ID','Name'), $this->OwnerID)
			)
		);
		
		
		
		$fields->removeByName('Filename');
		$fields->removeByName('Name');
		$fields->removeByName('Content');
		$fields->removeByName('Sort');
		$fields->removeByName('Parent');
		$fields->removeByName('ShowInSearch');
		$fields->removeByName('Main');
		$fields->removeByName('BackLinkTracking');
		
		//gallery - unsure what to do with these just yet
		$fields->removeByName('GalleryID');
		$fields->removeByName('GalleryClassName');
		
		return $fields;
	}
	
}
?>