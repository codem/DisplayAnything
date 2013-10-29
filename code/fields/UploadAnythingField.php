<?php
/**
* UploadAnything, DisplayAnything
* @copyright Codem 2011
* @author <a href="http://www.codem.com.au">James Ellis</a>
* @note UploadAnything is a base abstract set of classes used for building file uploaders, galleries and file managers in Silverstripe 2.4.4+. DisplayAnything extends and overrides functionality provided by UploadAnything to do just that. You can use UploadAnything natively as a simple file uploader and management tool or build your own file management tool off it.
* @note relies on and uses the Valums File Uploader ( http://github.com/valums/file-uploader )
* @license BSD
* @note 
		<h4>Background</h4>
		<p>Handle file uploads via XHR or standard uploads.</p>
		<h4>Features</h4>
		<ul>
			<li>Security: uses a mimetype map, not file extensions to determine an uploaded file type</li>
			<li>Integration: uses system settings for upload file size</li>
			<li>Usability: Multiple file uploading in supported browsers (not Internet Explorer)</li>
			<li>Drag and Drop in supported browsers (Chrome, Firefox, Safari)
			<li>XHR file uploading</li>
			<li>100% Flash Free - no plugin crashes or other futzing with incomprehensible errors!</li>
			<li>Has Zero Dependencies on DataObjectManager or Uploadify</li>
			<li>Designed to work with ComplexTableField</li>
			<li>Not reliant on jQuery or ProtoType</li>
			<li>Documented, extendable class</li>
			<li>$_REQUEST not used</li>
		</ul>
* @note that file extensions are not used
* @note usage

	try {
		$uploader = new UploadAnythingField();
		$uploader
			->SetTargetLocation('/path/to/some/subdirectory of ASSETS_PATH')
			->OverwriteFile(FALSE/TRUE)
			->SetMimeTypes()
			->ConfigureUploader( array('action' => '/path/to/upload') );
	} catch (Exception $e) {
		//an error occurred setting up the uploader
	}
*/

/**
 * UploadAnythingField()
 */
class UploadAnythingField extends ComplexTableField {
	private $file = FALSE;
	private $fileKey = 'qqfile';
	private $returnValue = array();
	private $allowed_file_types = array();//an associative array, key is the mime type, value is the extension without a .
	private $tmp_location = array();
	private $upload_file_name = "";
	private $overwrite_file = FALSE;
	private $target_location = "";
	private $configuration = array();
	protected $itemsClass = FALSE;//used by DisplayAnythingGalleryField for multiple items handling
	
	public $resize_method = "CroppedImage";
	public $thumb_width = 120;
	public $thumb_height = 120;
	public $show_help = TRUE;//FALSE to not show Upload Help text
	
	//default file and directory permissions
	//if your web server runs as a specific user, these can be altered to make the rw for only that user
	public $file_permission = 0644;
	public $directory_permission = 0755;
	
	//public $requirementsForPopupCallback = "popupRequirements";
	
	public function __construct($controller, $name, $sourceClass, $fieldList = NULL, $detailFormFields = NULL, $sourceFilter = "", $sourceSort = "", $sourceJoin = "") {
		parent::__construct($controller, $name, $sourceClass, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);
		$this->SetMimeTypes();
		self::LoadCSS();
	}
	
	/**
	 * SetFileKey()
	 * @note allow override of filekey used in upload, allows replacement to hook into Upload()
	 */
	public function SetFileKey($fileKey) {
		$this->fileKey = $fileKey;
		return $this;
	}
	
	/**
	 * SetPermissions()
	 */
	public function SetPermissions($file = 0644, $directory = 0755) {
		$this->file_permission = $file;
		$this->directory_permission = $directory;
		return $this;
	}
	
	public function GetFilePermission() {
		return $this->file_permission;
	}

	public function GetDirectoryPermission() {
		return $this->directory_permission;
	}
	
	public static function ToBytes($str){
		$val = trim($str);
		$last = strtolower($str[strlen($str)-1]);
		switch($last) {
			case 'g': $val *= 1024;
			case 'm': $val *= 1024;
			case 'k': $val *= 1024;
		}
		return $val;
	}
	
	/**
	 * SetMimeTypes()
	 * @note by default we just allow images
	 * @param $mimeTypes pass in key=>value pairs to override. The value is the file extension (e.g 'jpg' for image.jpg)
	 * @note are you unsure about mimetypes ? http://www.google.com.au/search?q=mimetype%20list
	 * @note So, why doesn't this use file extensions?
	 * <blockquote>File Extensions are part of the file name and have no bearing on the contents of the file whatsoever. 'Detecting' file content by matching the characters after the last "." may provide a nice string to work with but in fact lulls the developer into a false sense of security.
	 		To test this, create a new PHP file, add '<?php print "I am PHP";?>' and save that file as 'image.gif' then upload it using an uploader that allows file Gif files. What happens when you browse to that file ?
	 		UploadAnything requires your developer (or you) to provide it a map of allowed mimetypes. Keys are mimetypes, values are a short blurb about the file. By default it uses the standard mimetypes for JPG, GIF and PNG files.
	 		If you are uploading a valid file and the UploadAnything MimeType checker is not allowing it, first determine it's mimetype and check against the whitelist you have provided. Some older software will save a file in older, non-standard formats.
	 		UploadAnything uses the 'type' value provided by the PHP file upload handler for standard uploads that populate $_FILES. For XHR uploads, UploadAnything uses finfo_open() if available, followed by an image mimetype check, followed by mime_content_type (deprecated). If no mimeType can be detected. UploadAnything refuses the upload, which is better than allowing unknown files on your server.
	 		
	 		If you are using the DisplayAnything file gallery manager, the Usage tab provides a method of managing allowed file types on a per gallery basis.
	 		</blockquote>
	 */
	final public function SetMimeTypes($mimeTypes = array()) {
		if(empty($mimeTypes)) {
			//nothing set, assume image upload for starters
			$this->allowed_file_types = array(
				'image/jpg' => 'jpg',
				'image/jpeg' => 'jpg',
				'image/png' => 'png',
				//IE sometimes uploads older pre standardized PNG files as this mimetype. Another feather in its cap
				'image/x-png' => 'png',
				'image/gif' => 'gif',
				'image/pjpeg' => 'jpg',
			);
		} else {
			$this->allowed_file_types = $mimeTypes;
		}
		return $this;
	}
	
	/**
	 * LoadUploadHandler()
	 * @note loads the correct handler depending on incoming data - if $_FILES is present, use the standard file save handler, if the XHR request is present, use the XHR backend
	 * @returns object
	 */
	protected function LoadUploadHandler() {
		if (isset($_GET[$this->fileKey])) {
			$this->file = new UploadAnything_Upload_XHR($this->fileKey, $this);
			$this->file->saveToTmp();//saves raw stream to tmp location
		} elseif (isset($_FILES[$this->fileKey])) {
			$this->file = new UploadAnything_Upload_Form($this->fileKey, $this);
		} else {
			throw new Exception("No upload handler was defined for this request");
		}
	}
	
	/**
	 * @note this is a compatibility function with File::setName() - as we aren't dealing with a File object at this point, we can't (and don't want to) all File::setName() as needs a File instance. Additionally, we remove deprecated ereg_replace and generally make cross platform file name demunging smarter.
	 * @param $name a raw file name without a file extension
	 * @returns string
	 */
	protected function CleanFileName($name) {
		//trim down the file of any extra leading or trailing crap
		$name = trim($name, " ._-");
		// replace one or more white space with a -
		$name = preg_replace("/\s+/","-", $name);
		//anything not in this range is removed, this must match File::setName regex
		$name = preg_replace("/[^A-Za-z0-9.+_\-]/", "", $name);
		return $name;
	}
	
	/**
	 * SetUploadFileName()
	 * @param $uploadPath wherever uploades are saving at
	 * @param $overwrite if TRUE will overwrite the current same-named file in that directory
	 * @note we ensure the filename here matches what SS' File object will morph it into
	 * @todo place a limit on our while loop here? Repeat after me: I don't like recursiveness.
	 */
	protected function SetUploadFileName($uploadPath, $overwrite = FALSE) {
		$pathinfo = pathinfo($this->file->getName());
		$filename = $pathinfo['filename'];
		$ext = $pathinfo['extension'];
		if(!$overwrite && file_exists($uploadPath . "/" . $filename . "." . $ext)) {
			$suffix = 0;
			while (file_exists($uploadPath . "/" . $filename . "." . $ext)) {
				//while the file exists, prepend a suffix to the file name
				$filename = $suffix . "_" . $filename;
				$suffix++;
			}
		}
		$cleaned = $this->CleanFileName($filename);
		if($cleaned == "") {
			throw new Exception("File error: the filename '{$filename}' is not supported");
		}
		$this->upload_file_name = $cleaned . "." . $ext;
		return $uploadPath . "/" . $this->upload_file_name;
	}
	
	public function GetFileName() {
		return $this->upload_file_name;
	}
	
	protected function GetMaxSize() {
		//returns whatever is the minimum allowed upload size - out of FILE or POST
		return min( array( self::GetUploadMaxSize(), self::GetPostMaxSize() ) );
	}
	
	public static function GetUploadMaxSize() {
		return self::ToBytes(ini_get('upload_max_filesize'));
	}
	
	public static function GetPostMaxSize() {
		return self::ToBytes(ini_get('post_max_size'));
	}
	
	final private function CheckAllowedSize() {
		$size = $this->file->getSize();
		
		if ($size == 0) {
			throw new Exception('File is empty');
		}
		
		$postSize = 
		$uploadSize = self::GetUploadMaxSize();
		$msize = round($size / 1024 / 1024, 2) . 'Mb';
		$postSizeMb = round($postSize / 1024 / 1024, 2) . 'Mb';
		$uploadSizeMb = round($uploadSize / 1024 / 1024, 2) . 'Mb';
		
		if ($size > $postSize) {
			throw new Exception("The server does not allow files of this size ({$msize}) to be uploaded. Hint: post_max_size is set to {$postSizeMb}");
		}
		
		if ($size > $uploadSize) {
			throw new Exception("The server does not allow files of this size ({$msize}) to be uploaded. Hint: upload_max_filesize is set to {$uploadSizeMb}");
		}
		
		return TRUE;
	}
	
	/**
	 * CheckAllowedType()
	 * @note grabs file and checks that the mimetype of the file is in our allowed mimetypes
	 */
	final private function CheckAllowedType() {
	
		if(empty($this->allowed_file_types)) {
			throw new Exception("No allowed file types have been defined - for security reasons this file cannot be uploaded.");
		}
		
		$allowed_list = "";
		foreach($this->allowed_file_types as $type=>$ext) {
			$allowed_list .= $type . ($ext ? " (" .strtolower($ext) . ")" : "") . ", ";
		}
		
		$mimeType = strtolower($this->file->getMimeType());
		if(!array_key_exists($mimeType, $this->allowed_file_types)) {
			throw new Exception("This file uploader does not allow files of type '{$mimeType}' to be uploaded. Allowed types: " .  trim($allowed_list, ", ") . ".");
		}
		return TRUE;
	}
	
	/**
	 * GetAllowedExtensions() used solely for client side validation on the filename
	 * @returns array
	 * @throws Exception
	 */
	final private function GetAllowedExtensions() {
		if(empty($this->allowed_file_types)) {
			throw new Exception("No allowed file types have been defined for this uploader.");
		}
		return array_unique(array_values($this->allowed_file_types));
	}
	
	protected function GetAllowedFilesNote() {
		return implode(",", $this->GetAllowedExtensions());
	}
	
	/**
	 * UploadResult()
	 * @note either returns whether the upload has succeeded or prints a JSON encoded string for the upload client
	 * @returns mixed
	 */
	public function UploadResult($return = FALSE) {
		if($return) {
			return $this->Success();
		} else {
			print htmlspecialchars(json_encode($this->returnValue), ENT_NOQUOTES);
			exit;
		}
	}
	
	/**
	 * GetReturnValue() gets current return value
	 * @returns mixed
	 */
	public function GetReturnValue() {
		return $this->returnValue;
	}


	/**
	 * Success() returns TRUE if returnValue is successful
	 * @returns boolean
	 */
	public function Success() {
		return isset($this->returnValue['success']) && $this->returnValue['success'];
	}
	
	/**
	 * ConfigureUploader()
	 * @param $configuration an array of configuration values (see http://valums.com/ajax-upload/)
	 * @note that action is handled internally and will be overwritten
	 * @note example configuration:
	 * 			<pre>array(
	 *					'action' => '/relative/path/to/upload',
	 *					'params' => array(), //note that params are passed as GET variables (not POST)
	 *					'allowedExtensions' => array(),//this is ignored and configured on the server side using mimetypes
	 *					'sizeLimit' => 0, //in bytes ?
	 *					'minSizeLimit' => 0, //in bytes ?
	 *					'debug' => true/false,
	 *					...
	 *				)</pre>
	 */
	public function ConfigureUploader($configuration = array()) {
		if(!is_array($configuration)) {
			throw new Exception('Incorrect configuration for UploadAnythingField');
		}
		$this->configuration = $configuration;
		return $this;
	}
	
	/**
	 * OverwriteFile()
	 * @note sets the file field to overwrite if a same-named file is found
	 */
	public function OverwriteFile($replace = FALSE) {
		$this->overwrite_file = $replace;
		return $this;
	}
	
	/**
	 * AssociateWith()
	 * @note associates the upload with the given dataobject.
	 * @param $dataobject object of type DataObject
	 * @param $pk an array of field names that make up the Primary Key for this DO. Most of the time it will be ID. If you have a complex data object,pass in the PK field names.
	 * @return object
	 * @deprecated
	 */
	public function AssociateWith($dataobject, $pk = array('ID')) {
		return $this;
	}
	
	/**
	 * SetTargetLocation()
	 * @param $location a subdirectory of the SS ASSETS_PATH
	 * @note doesn't have to exist just yet as the uploader will create it
	 */
	final public function SetTargetLocation($location) {
		$this->target_location = $location;
		return $this;
	}
	
	/**
	 * CanUpload()
	 * @note can the current member upload ?
	 * @todo upload permissions
	 */
	protected function CanUpload() {
	
		$can = ini_get('file_uploads');
		if(!$can) {
			throw new Exception('File uploads are not enabled on this system. To enable them, get your administrator to set file_uploads=1 in php.ini');
		}
	
		$member = Member::currentUser();
		if(empty($member->ID)) {
			throw new Exception("You must be signed in to the administration area to upload files");
		}
		return $member;
	}
	
	/**
	 * UnlinkFile() unlinks a target file
	 * @returns boolean
	 */
	private function UnlinkFile($target) {
		if(is_writable($target)) {
			unlink($target);
		}
	}
	
	/**
	 * LoadScript()
	 * @note override this method to use your own CSS. Changing this may break file upload layout.
	 */
	public static function LoadScript() {
		
		//have to use bundled jQ or CMS falls over in a screaming heap
		Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		Requirements::javascript(THIRDPARTY_DIR."/jquery-livequery/jquery.livequery.js");
		Requirements::javascript(THIRDPARTY_DIR."/jquery-ui/jquery-ui-1.8rc3.custom.js");
		
		Requirements::block(THIRDPARTY_DIR . "/firebug-lite/firebugx.js");//block this out, the little bastard
		Requirements::javascript("display_anything/javascript/file-uploader/client/fileuploader.js");
		Requirements::javascript("display_anything/javascript/display.js");

	}
	
	/**
	 * LoadCSS()
	 * @note override this method to use your own CSS. Changing this may break file upload layout.
	 */
	public static function LoadCSS() {
		Requirements::css("display_anything/css/display.css");
		Requirements::css("display_anything/javascript/file-uploader/client/fileuploader.css");
	}

	/**
	 * LoadAdminCSS
	 * @deprecated use self::LoadCSS()
	 */
	public static function LoadAdminCSS() {
		Requirements::css("display_anything/css/display.css");
	}
	
	/**
	 * LoadAssets()
	 * @note override this method to use your own jquery lib file. You should provide a recent version or uploads may fail.
	 */
	protected function LoadAssets() {
		self::LoadScript();
		self::LoadCSS();
	}
	
	public static function popupRequirements() {
		self::LoadAssets();
	}
	
	/**
	 * GetUploaderConfiguration()
	 * @note you can override this for custom upload configuration. This configuration is for the client uploader and it's worth noting that all options here can be easily changed by anyone with enough browser console knowledge. Validation happens on the server
	 * @todo file extensions allowed
	 * @todo min size, max size per uploader spec
	 * @see "### Options of both classes ###" in the FileUploader client readme.md
	 * @todo check if 'record' is still required...shouldn't be relied on
	 */
	public function GetUploaderConfiguration() {
		try {
			//work out the upload location.
			$this->configuration['action'] = $this->Link('Upload');
			$this->configuration['reload'] = $this->Link('ReloadList');
			
			if(!isset($this->configuration['params'])) {
				$this->configuration['params'] = array();
			}
			
			$this->configuration['allowedExtensions']  = $this->GetAllowedExtensions();
	
			//these options are not supported in all browsers
			$this->configuration['sizeLimit'] = $this->GetMaxSize();
			$this->configuration['minSizeLimit'] = 0;
			
			if(!isset($this->configuration['maxConnections'])) {
				$this->configuration['maxConnections'] = 3;
			}
			
			$this->configuration['params']['record'] = $this->GetAssociatedRecord();
			$string = htmlentities(json_encode($this->configuration), ENT_QUOTES, "UTF-8");
			//print $string;
			return $string;
		} catch (Exception $e) {}
		
		return "";
	}
	
	/**
	 * GetAssociatedRecord()
	 * @note can override this if you wish
	 * @deprecated
	 */
	private function GetAssociatedRecord() {
		$list =  array();
		$list['ID'] = $this->controller->ID;
		return array(
			'd' => get_class($this->controller),
			'p' => $list,
		);
	}
	
	/**
	 * FieldPrefix()
	 * @note HTML to place before the form field HTML
	 */
	public function FieldPrefix() {
		return "";
	}
	
	/**
	 * FieldSuffix()
	 * @note HTML to place after the form field HTML
	 */
	public function FieldSuffix() {
		$list = $this->GetFileList();
		if(empty($list)) {
			$list = "<div class=\"file-uploader-item\"><p>No files have been associated yet...</p></div>";
		}
		$html = "<div class=\"file-uploader-list\">{$list}</div>";
		if($this->show_help) {
			$html .= "<div class=\"help\"><div class=\"inner\">"
					. " <h4>Upload help</h4><ul>"
					. " <li><strong>Chrome</strong>, <strong>Safari</strong> and <strong>Firefox</strong> support multiple image upload (Hint: 'Ctrl/Cmd + click' to select multiple images in your file chooser)</li>"
					. "<li>In <strong>Firefox</strong>, Safari and <strong>Chrome</strong> you can drag and drop images onto the upload button</li>"
					. "<li>Internet Explorer <= 9 does not support multiple file uploads or drag and drop of files.</li>"
					. "</ul>"
					. "</div></div>";
		}
		return $html;
	}
	
	/**
	 * FileEditorLink()
	 * @note provide a link to edit this item
	 * @returns string
	 */
	protected function FileEditorLink($file, $relation, $action = "edit") {
		$link = "";
		switch($relation) {
			case "self":
				$link = "";
				break;
			case "single":
				$link = Controller::join_links(Director::baseURL(), $this->Link(), 'item/' . $file->ID . '/' . $action);
				break;
			case "gallery":
				$parts = parse_url($this->Link());
				$link = Controller::join_links(Director::baseURL(), $parts['path'] . '/item/' . $this->controller->{$this->name}()->ID . '/DetailForm/field/' . $this->itemsClass . '/item/' . $file->ID . '/' . $action . '/?' . (isset($parts['query']) ? $parts['query'] : ''));
				break;
			default:
				throw new Exception("Unhandled relation: {$relation}");
				break;
		}
		return $link;
	}
	
	/**
	 * GetFileListItem()
	 * @note returns an HTML string representation of one gallery item
	 * @note in gallery mode /admin/EditForm/field/ImageGallery/item/1/ DetailForm/field/GalleryItems/item/78/edit?SecurityID=xyz
	 * @note in single mode /admin/EditForm/field/FeatureImage/item/80/edit?SecurityID=xyz
	 * DeleteLink
	 * @note in gallery mode: /admin/EditForm/field/ImageGallery/item/1/DetailForm/field/GalleryItems/item/78/delete?SecurityID=xyz
	 * @note in single mode /admin/EditForm/field/FeatureImage/item/80/delete?SecurityID=xyz
	 * @returns string
	 * @param $file an UploadAnythingFile object or a class extending it
	 * @param $relation one of self, single or gallery
	 */
	protected function GetFileListItem($file, $relation) {
		$html = "";
		
		$editlink = $this->FileEditorLink($file, $relation, "edit");
		$deletelink = $this->FileEditorLink($file, $relation, "delete");
		
		$html .= "<div class=\"file-uploader-item\" rel=\"{$file->ID}\">";
		$html .= "<a class=\"editlink\" href=\"{$editlink}\" title=\"" . htmlentities($file->Name, ENT_QUOTES) . "\">";
		
		//try to create a thumb (if it is one)
		$path = BASE_PATH . "/" . $file->Filename;
		$is_image = $file->IsImage($path);
		
		$thumb = "[no file found]";
		
		if(!file_exists($path)) {
			$thumb = "<br />File does not exist<br />";
		} else if($is_image) {
			$tag = $file->Thumbnail($this->resize_method, $this->thumb_width, $this->thumb_height);
			if($tag) {
				$thumb = $tag;
			} else {
				$thumb = "<img src=\"" . SAPPHIRE_DIR . "/images/app_icons/image_32.gif\" width=\"24\" height=\"32\" alt=\"unknown image\" /><br />(no thumbnail)";
			}
		} else {
			//TODO: get a nice file icon...
			$thumb = UploadAnythingFile::GetFileIcon();
		}
		
		$html .= "<div class=\"thumb\">{$thumb}</div>";
		$html .= "<div class=\"caption\"><p>" . substr($file->Title, 0, 16)  . "</p></div>";
		$html .= "</a>";
		$html .= "<div class=\"tools\">";
		$html .= "<a class=\"deletelink\" href=\"{$deletelink}\"><img src=\"" . Director::BaseURL() . CMS_DIR . "/images/delete.gif\" alt=\"delete\" /></a>";
		$html .= "<img src=\"" . rtrim(Director::BaseURL(), "/") . "/display_anything/images/sort.png\" title=\"drag and drop to sort\" alt=\"drag and drop to sort\" />";
		$html .= "</div>";
		$html .= "</div>";
		
		return $html;
	}
	
	/**
	 * GetFileList()
	 * @note returns an HTML file list. If the method 'UploadAnythingFileList' is implemented in the related dataobject, that is used and must return a string (i.e you author the list), if not, it's rendered as a list of inline items
	 * @return string
	 * @todo is "ID" ok to be hardcoded
	 */
	private function GetFileList() {
		$html = "";
		if(method_exists($this->controller,'DisplayAnythingFileList')) {
			return $this->{$this->controller}->DisplayAnythingFileList();
		} else {
			$relation = $this->DataTypeRelation();
			switch($relation) {
				case "self":
					//self - no need to show anything
					if($this->controller instanceof File) {
						$html = $this->GetFileListItem($this->controller, $relation);
					} else {
						$html = "DisplayAnything cannot represent this object";
					}
					break;
				case "single";
					//the relationship: the related dataobject has one of this file
					$field = $this->name . "ID";
					$id = $this->controller->{$this->name . 'ID'};
					if(!empty($id)) {
						$file = DataObject::get_by_id('File', $id);
						if($file) {
							$html = $this->GetFileListItem($file, $relation);
						}
					} else {
						$html = "";
					}
					break;
				case "gallery":
					//the related dataobject has many files
					$info = $this->GetComponentInfo();
					$files = array();
					if(isset($info['childClass'])) {
						//$files = $this->controller->{$this->name}()->{$this->itemsClass}();
						$where = Convert::raw2sql($info['joinField']) . " = " . Convert::raw2sql($this->controller->{$this->name}()->ID);
						$files = DataObject::get(
							$info['childClass'],
							$where,
							'`File`.`Sort` ASC, `File`.`Created` DESC'
						);	
					} else {
						//fallback ?
						throw new Exception("Failed to get valid component info for the gallery. This generally means the dataobject association is not correct.");
					}
					
					if($files) {
						foreach($files as $file) {
							$html .= $this->GetFileListItem($file, $relation);
						}
					}
					break;
				default:
					throw new Exception("Unhandled relation: {$relation}. File could not be saved.");
					break;
			}
		}
		return $html;
	}

	/**
	 * Field()
	 * @note just returns the field. Note that the FileUploader.js handles all the HTML machinations, we just provide a container
	 */
	function Field() {
		$id = $this->id();
		$html = "";
		if($id == "") {
			$html .= "<p>No 'id' attribute was specified for the file upload field. File uploads cannot take place until you or your developer provides this information to UploadAnything</p>";
		} else {
			//set up the upload
			$html .= "<div class=\"uploadanything-upload-box\"  id=\"{$id}\" rel=\"{$this->GetUploaderConfiguration()}\">Loading uploader...</div>";
		}
		return $html;
	}
	
	/**
	 * FieldHolder()
	 * @note returns the form field
	 * @returns string
	 */
	public function FieldHolder() {
		$this->LoadAssets();
		
 		$reload = $this->Link('ReloadList');
 		$resort = $this->Link('SortItem');
 		
		$Title = $this->XML_val('Title');
		$Message = $this->XML_val('Message');
		$MessageType = $this->XML_val('MessageType');
		$RightTitle = $this->XML_val('RightTitle');
		$Type = $this->XML_val('Type');
		$extraClass = $this->XML_val('extraClass');
		$Name = $this->XML_val('Name');
		$Field = $this->XML_val('Field');
		
		// Only of the the following titles should apply
		$titleBlock = "<label class=\"left\" for=\"{$this->id()}\">Actions: <strong><a href=\"{$reload}\" class=\"reload reload-all\">reload</a></strong><a class=\"sortlink\" href=\"{$resort}\">sort</a>";
		$titleBlock .= " <span>Max. file size: " . round($this->GetMaxSize() / 1024 / 1024, 2) . "Mb,";
		$titleBlock .= " File types: " . $this->GetAllowedFilesNote() . "</span>";
		$titleBlock .= "</label>";
		
		// $MessageType is also used in {@link extraClass()} with a "holder-" prefix
		$messageBlock = (!empty($Message)) ? "<span class=\"message $MessageType\">$Message</span>" : "";
		
		return <<<HTML
<div class="file-uploader">
	<div id="$Name" class="field $Type $extraClass">
			{$titleBlock}
			<div class="middleColumn">
				$Field
				{$this->FieldPrefix()}
				{$this->FieldSuffix()}
				<div class="break"></div>
			</div>
	</div>
</div>
HTML;
	}
	
	/**
	 * Replace()
	 * @note replaces the current file by simply running an Upload() using the 'self' datatype relation
	 * @returns boolean
	 */
	final public function Replace($key = "replace") {
		if(!empty($_FILES[$key]['tmp_name'])) {
			$this->SetFileKey($key);
			return $this->Upload(TRUE);
		}
		return FALSE;
	}
	
	
	/**
	 * UpdateCurrentRecord()
	 * @note need to catch any updateFilesystem exceptions here... ignore them. Some of them don't pertain to us
	 * @note onBeforeWrite() calls this->ReplaceFile() in UploadAnythingFile() which is first called by saveComplexTableField
	 * 	because of this, we don't update this with the ORM as File->write() will end up calling this again (and again (and again))
	 * @throws Exception
	 * @returns TRUE
	 */
	protected function UpdateCurrentRecord($uploadDirectory, $filename) {
		//updating the current file
		if(!$this->controller instanceof File) {
			throw new Exception("Replacing file but current controller is not an instance of File");
		}
		
		//just replacing the file - do this and return
		$filename_path_current = BASE_PATH . '/' . $this->controller->Filename;
		
		$query = "UPDATE File SET Name='" . Convert::raw2sql($filename) . "', Filename='" . Convert::raw2sql($uploadDirectory . "/" . $filename) . "' WHERE ID = " . Convert::raw2sql($this->controller->ID);
		
		$update = DB::query($query);
		if($update) {
			if(is_writable($filename_path_current)) {
				//remove the old file
				unlink($filename_path_current);
			}
			$this->returnValue = array('success'=>true);
			return TRUE;
		} else {
			throw new Exception("Failed to replace the current file with your upload.");
		}
	}
	
	/**
	* Upload
	* @note handles a file upload to $uploadPath. Upload() saves the file and links it to the dataobject, returning the saved file ID. If an error occurs an exception is thrown, causing the correct returnValue to be set.
	* @param $return if TRUE Upload will return a value rather than exit - which is the default for XHR uploads (after printing a JSON string)
	* @returns mixed
	* @note $this->file refers to the upload handler instance dealing with a file that has been uploaded, not the SS File object
	*/
	final public function Upload($return = FALSE) {
		try {
		
			$relation = $this->DataTypeRelation();
			
			//current member if they can upload, throws an exception and bails if not
			$member = $this->CanUpload();
			
			//set this->file to the correct handler
			$this->LoadUploadHandler();
			
			//if not set, create a target location
			if($this->target_location == "") {
				$this->target_location = "Uploads";
			}
		
			//final location of file
			$targetDirectory = "/" . trim($this->target_location, "/ ");
			$uploadPath = "/" . trim(ASSETS_PATH, "/ ") . $targetDirectory;
			$uploadDirectory = ASSETS_DIR . $targetDirectory;
			
			if(!is_writable(ASSETS_PATH)) {
				throw new Exception("Server error. This site's assets directory is not writable.");
			}
		
			if(!file_exists($uploadPath)) {
				mkdir($uploadPath, $this->directory_permission, TRUE);
			}
			
			if (!is_writable($uploadPath)){
				throw new Exception("Server error. Upload directory '{$uploadDirectory}' isn't writable.");
			}
			
			if (!$this->file){
				throw new Exception('No file handler was defined for this upload.');
			}
			
			$this->CheckAllowedSize();
			
			$this->CheckAllowedType();
			
			//now save the file
			//this this point we aren't dealing with the File object, just an upload
			$target = $this->SetUploadFileName($uploadPath, $this->overwrite_file);
			
			//saves the file to the target directory
			$this->file->save($target);
			
			//here ? then the file save to disk has worked
			
			try {
				//catch some internal nerdy errors here so they don't bubble up
				
				$filename =  $this->GetFileName();
				
				if($relation == "self" && $this->UpdateCurrentRecord($uploadDirectory, $filename)) {
					return TRUE;
				}
				
				
				//make a folder record (optionally makes it on the file system as well, although this is done in this->file->save()
				$folder = Folder::findOrMake($targetDirectory);//without ASSETS_PATH !
				if(empty($folder->ID)) {
					$this->UnlinkFile($target);
					throw new Exception('No folder could be assigned to this file');
				}
				
				switch($relation) {
					case "single";
						
						$file = new UploadAnythingFile();//TODO - this should match the file type provided
						$file->Name = $filename;
						$file->Title = $filename;
						//$file->Filename = $filename;//required ?
						$file->ShowInSearch = 0;
						$file->ParentID = $folder->ID;
						$file->OwnerID = $member->ID;
						
						//write the file
						$id = $file->write();
						if($id) {
							//the relationship: the related dataobject has one of this file
							$this->controller->{$this->name . 'ID'} = $id;
							$this->controller->write();
						} else {
							throw new Exception("The file '{$filename}' could not be saved (1).");
						}
						break;
					case "gallery":
						//add this file to the relation component
						//the type of file that is the relation
						$info = $this->GetComponentInfo();
						if(!$info || !isset($info['childClass'])) {
							throw new Exception("Error: {invalid component info detected. This generally means the gallery association with the page is not correct.");
						}
						$file_class = $info['childClass'];
						$file = new $file_class;
						if(!isset($info['joinField'])) {
							throw new Exception("Error: {$file_class} does not have a has_one relation linking it to {$item['ownerClass']}.");
						} else if($file instanceof File) {
							$file->Name = $filename;
							$file->Title = $filename;
							//$file->Filename = $filename;//required?
							$file->ShowInSearch = 0;
							$file->ParentID = $folder->ID;
							$file->OwnerID = $member->ID;
							$file->{$info['joinField']} = $this->controller->{$this->name}()->ID;//link it to the owner gallery
							//write the file
							$id = $file->write();
							if(!$id) {
								throw new Exception("The file '{$filename}' could not be saved (2).");
							}
						} else {
							throw new Exception("Error: {$file_class} is not child of 'File'. {$file_class} should extend 'File' or a child of 'File'");
						}
						break;
					default:
						throw new Exception("Unhandled relation: {$relation}. File could not be saved.");
						break;
				}
			} catch (Exception $e) {
				$this->UnlinkFile($target);
				throw new Exception("The file could not be uploaded. File save failed with error: " . $e->getMessage());
			}
			
			//here ? no exceptions were thrown
			$this->returnValue = array('success'=>true);
			
		} catch (Exception $e) {
			$this->returnValue = array('error' => $e->getMessage());
		}
		
		//trigger a JSON return value
		return $this->UploadResult($relation == "self");
	}
	
	/**
	 * GetComponentInfo()
	 * @note gets the component information for the associated dataobject
	 * @returns mixed
	 */
	final private function GetComponentInfo() {
		try {
			return $this->controller->{$this->name}()->{$this->itemsClass}()->getComponentInfo();
		} catch (Exception $e) {
			return FALSE;
		}
	}
	
	/**
	 * ReloadList()
	 * @note fired onComplete of a file upload and other actions
	 */
	final public function ReloadList() {
		return $this->GetFileList();
	}
	
	/**
	 * SortItem()
	 * @note HTTP POST API to update sort order in a gallery, returns the number of sorted items
	 * @note we don't plug into the ORM here to make for faster uploading
	 * @return integer
	 */
	final public function SortItem() {
		$success = 0;
		if(!empty($_POST['items']) && is_array($_POST['items'])) {
			foreach($_POST['items'] as $item) {
				if(!empty($item['id'])) {
					$sort = (isset($item['pos']) ? (int)$item['pos'] : 0);
					//run a quick query and bypass the ORM
					$query = "UPDATE `File` SET Sort = '" . Convert::raw2sql($sort) . "' WHERE ID = '" . Convert::raw2sql($item['id']) . "'";
					$result = DB::query($query);
					if($result) {
						$success++;
					}
				}
			}
		}
		return $success;
	}
	
	/**
	 * DataTypeRelation()
	 * @note determines the data type relation of the file to the controller
	 * @returns string 'self' (replace current file) , 'gallery' (has_many) or 'single' (has_one)
	 * @throws Exception
	 */
	final private function DataTypeRelation() {
	
		$controller = get_class($this->controller);
	
		if($this->controller->has_one($this->name)) {
			if($this->itemsClass) {
				if($this->controller->{$this->name}()->has_many($this->itemsClass)) {
					return "gallery";
				} else {
					throw new Exception("Controller '{$controller}' has one '{$this->name}' but '{$this->name}' does not have many '{$this->itemsClass}'");
				}
			} else {
				return "single";
			}
		} else if($controller == $this->name) {
			//replacing current file
			return "self";
		}
		
		throw new Exception("The datatype relation between '{$this->name}' and '{$controller}' is neither self, has_one or has_many. The file could not be saved");
	}

}

/**
 * UploadAnythingFileField()
 * @note returns a simple form file field input but validation and saving is handled by UploadAnythingField
 */
class UploadAnythingFileField extends FileField {

	public function __construct($name, $title = null, $value = null, $form = null, $rightTitle = null) {
		parent::__construct($name, $title, $value, $form, $rightTitle);
	}
	
	public function Field() {
		return $this->createTag(
			'input', 
			array(
				"type" => "file", 
				"name" => $this->name,
				"id" => $this->id(),
				"tabindex" => $this->getTabIndex()
			)
		) . 
		$this->createTag(
			'input', 
		  	array(
		  		"type" => "hidden", 
		  		"name" => "MAX_FILE_SIZE", 
		  		"value" => UploadAnythingField::GetUploadMaxSize(),
				"tabindex" => $this->getTabIndex()
		  	)
		);
	}
	
	public function FieldHolder() {
		$Title = $this->XML_val('Title');
		$Message = $this->XML_val('Message');
		$MessageType = $this->XML_val('MessageType');
		$RightTitle = $this->XML_val('RightTitle');
		$Type = $this->XML_val('Type');
		$extraClass = $this->XML_val('extraClass');
		$Name = $this->XML_val('Name');
		$Field = $this->XML_val('Field');
		
		// $MessageType is also used in {@link extraClass()} with a "holder-" prefix
		$messageBlock = (!empty($Message)) ? "<span class=\"message $MessageType\">$Message</span>" : "";
		
		return <<<HTML
<div id="$Name" class="field $Type $extraClass">
	{$messageBlock}
	<div class="middleColumn">
		$Field
		<div class="break"></div>
	</div>
</div>
HTML;
	}
	
}

/**
 * UploadAnything_Upload
 * @note abstract class used to define structure for upload in use
 */
abstract class UploadAnything_Upload {
	public function save() {
		//save should be implemented in the child class
		$this->cleanup();
	}
	
	public function cleanup() {
		//cleanup should be set in child class
	}
}

/**
 * UploadAnything_Upload_XHR
 * @note an XHR upload handler
 */
class UploadAnything_Upload_XHR {

	private $fileKey;
	private $tmp_location;
	private $tmp_handle;
	
	private $field;
	
	public function __construct($fileKey, $field) {
		$this->fileKey = $fileKey;
		$this->field = $field;
	}
	
	/**
	* Save the input stream to a path on disk
	* @return boolean TRUE on success
	*/
	public function saveToTmp() {
		$input = fopen("php://input", "r");
		$this->tmp_location = tempnam(sys_get_temp_dir(), 'xhr_upload_');
		$this->tmp_handle = fopen($this->tmp_location, "w");
		stream_copy_to_stream($input, $this->tmp_handle);
		fclose($input);
		return true;
	}
	
	//save file, at this point all checks have been done
	public function save($path) {
		if(file_exists($this->tmp_location)) {
			$result = rename($this->tmp_location, $path);
			if(!$result) {
				throw new Exception('Could not save uploaded file. Can the destination path be written to?');
			}
			@chmod($path, $this->field->GetFilePermission());
		} else {
			throw new Exception('Could not save uploaded file. The uploaded file no longer exists.');
		}
		return TRUE;
	}
	
	public function cleanup() {
		if(is_resource($this->tmp_handle)) {
			fclose($this->tmp_handle);
		}
		if(file_exists($this->tmp_location)) {
			unlink($this->tmp_location);
		}
	}
	
	public function getTmpFile() {
		return $this->tmp_location;
	}
	
	public function getName() {
		return $_GET[$this->fileKey];
	}
	public function getSize() {
		if(file_exists($this->tmp_location)) {
			return filesize($this->tmp_location);
		}
		return 0;
	}
	public function getMimeType() {
		$mimeType = UploadAnythingFile::MimeType($this->tmp_location);
		if(!$mimeType) {
			throw new Exception("Cannot reliably determine the mime-type of this file");
		}
		
		return $mimeType;
	}

}

/**
* UploadAnything_Upload_Form
* @note Handle file uploads via regular form post (uses the $_FILES array)
*/
class UploadAnything_Upload_Form {

	private $fileKey;
	private $field;
	
	public function __construct($fileKey, $field) {
		$this->fileKey = $fileKey;
		$this->field = $field;
	}
	
	/**
	* Save the file to the specified path
	* @return boolean TRUE on success
	*/
	function save($path) {
		if(!is_uploaded_file($_FILES[$this->fileKey]['tmp_name'])) {
			throw new Exception("The server did not allow this file to be saved as it does not appear to be a file that has been uploaded.");
		}
		if(!move_uploaded_file($_FILES[$this->fileKey]['tmp_name'], $path)){
			throw new Exception('Could not save uploaded file. Can the destination path be written to?');
		}
		
		@chmod($path, $this->field->GetFilePermission());
		return TRUE;
	}
	function getName() {
		return $_FILES[$this->fileKey]['name'];
	}
	function getSize() {
		return $_FILES[$this->fileKey]['size'];
	}
	function getMimeType() {
		return $_FILES[$this->fileKey]['type'];
	}
}
?>