<?php
/**
 * UploadAnythingFile()
 * @note our file class, extends File and provides extra bits and bobs
 * @note DisplayAnythingFile extends this class and should be used instead, you can extends this class if you like to provide your own File functionality
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
	 * updateFilesystem()
	 * @note override parent File::updateFilesystem can catch its Exceptions
	 */
	public function updateFilesystem() {
		try {
			parent::updateFilesystem();
		} catch (Exception $e) {
			//ignore what happens above
		}
		return TRUE;
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
	
	/**
	 * GetMimeTypes() returns the current mimetypes associated with the gallery this file is in
	 * @note this will return an empty array if UploadAnything is in single file mode (has_one)
	 * @return array
	 */
	protected function GetMimeTypes() {
		$mimetypes = array();//use defaults
		try {
			$gallery = $this->Gallery();
			if($gallery && ($usage = $gallery->Usage())) {
				$mimetypes = explode(",", $usage->MimeTypes);
				if($extra = $this->Gallery()->ExtraMimeTypes) {
					$mimetypes = array_merge($mimetypes, implode("," , $extra));
				}
			}
		} catch (Exception $e) {
			return array();
		}
		return array_flip($mimetypes);
	}
	
	/**
	 * FileReplacementField() returns the file replacement field, used to replace this one file in the gallery
	 * @return object
	 */
	protected function FileReplacementField() {
		$this->replace_use_self = FALSE;
		if($this->replace_use_self) {
			//use the XHR replace field
			//this is highly experimental and won't work (just yet) - if you want to debug, set TRUE above
			$replace = new UploadAnythingField($this, get_class($this), 'File');
			$replace->show_help = FALSE;
			$replace->SetMimeTypes($this->GetMimeTypes());
		} else {
			//standard file input - save handled in onBeforeWrite()
			$sourceClass = $name = get_class($this);//replacing self
			$replace = new UploadAnythingFileField("replace", "");
		}
		return $replace;
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
		
		$thumbnail = $this->Thumbnail('SetWidth', 400);
		if(empty($thumbnail)) {
			$thumbnail = self::GetFileIcon();
		}
		
		$replace = $this->FileReplacementField();
		
		$fields->addFieldsToTab('Root.FilePreview', new FileField('dummy_field','dummy_field'));//dummy field to trigger the correct enctype, Form doesn't allow enctype to be manually set if the field is included in our literal field below
		
		$fields->addFieldsToTab(
			'Root.FilePreview',
			array(
				//and some meta
				new LiteralField(
					'FileMetaData',
<<<HTML
<table class="uploadanythingfile_meta">
	<tbody>
		<tr><th>Size</th><td>{$meta['size']}</td></tr>
		<tr><th>Width</th><td>{$meta['width']}</td></tr>
		<tr><th>Height</th><td>{$meta['height']}</td></tr>
		<tr><th>Type</th><td>{$meta['mimetype']}</td></tr>
		<tr class="replace"><th>Replace with</th><td class="field">{$replace->FieldHolder()}</td></tr>
		<tr><th>Thumbnail</th><td>{$thumbnail}</td></tr>
	</tbody>
</table>
HTML
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
	
	public static function GetFileIcon($type = "") {
		return "<img src=\"" . SAPPHIRE_DIR . "/images/app_icons/generic_32.gif\" width=\"24\" height=\"32\" alt=\"file icon\" />";
	}
	
	/**
	 * ReplaceFile()
	 * @note replaces the current file on disk for this File object. Will trigger Upload() using an HTTP POST upload (_FILES method)
	 * @throws Exception
	 * @return boolean
	 */
	private function ReplaceFile() {
		$key = "replace";
		if(!empty($_FILES[$key]) && is_uploaded_file($_FILES[$key]['tmp_name'])) {
			$field = new UploadAnythingField($this, get_class($this), 'File');
			$field->SetMimeTypes($this->GetMimeTypes());
			$field->SetFileKey($key);
			$field->Replace();
			$success = $field->Success();
			if(!$success) {
				$return = $field->GetReturnValue();
				throw new Exception(isset($return['error']) ? $return['error'] : 'Unhandled Error');
			} else {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * onAfterWrite() handle post write actions, in our case it's possible replacing the file
	 * @todo saveComplexTableField is being called here before this is being called, resulting in is_uploaded_file errors
	 * @note maybe UploadAnythingField should not be a ComplexTableField ?
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();
		try {
			$this->ReplaceFile();
		} catch (Exception $e) {
			$valid = new ValidationResult();
			$valid->error($e->getMessage());
			throw new ValidationException($valid, $e->getMessage());
			return FALSE;
		}
		return TRUE;
	}
	
	public function getRequirementsForPopup() {
		UploadAnythingField::LoadScript();
		UploadAnythingField::LoadAdminCSS();
	}
	
}
?>