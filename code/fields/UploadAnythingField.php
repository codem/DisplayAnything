<?php
/**
* UploadAnything
* @copyright Codem 2011
* @author <a href="http://www.codem.com.au">James Ellis</a>
* @note relies on and uses the Valums File Uploader ( http://github.com/valums/file-uploader )
* @license BSD
* @note 
		<h4>Background</h4>
		<p>Handle file uploads via XHR or standard uploads.</p>
		<h4>Features</h4>
		<ul>
			<li>Security: uses a mimetype map, not file extensions to determine an uploaded file type</li>
			<li>Integration: uses system settings for upload file size</li>
			<li>Multiple file uploading in supported browsers (not Internet Explorer)</li>
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
			->ReplaceFile(FALSE/TRUE)
			->SetMimeTypes()
			->ConfigureUploader( array('action' => '/path/to/upload') );
	} catch (Exception $e) {
		//an error occurred setting up the uploader
	}
*/

/**
 * UploadAnythingField()
 * @note the upload handler, that invokes the correct uploader depending on the incoming data
 * @note this is actually a ComplexTableField, just rendered differently
 */
class UploadAnythingField extends ComplexTableField {
	private $file = FALSE;
	private $fileKey = 'qqfile';
	private $returnValue = array();
	private $allowed_file_types = array();
	private $tmp_location = array();
	private $upload_file_name = "";
	private $replace_file = FALSE;
	private $target_location = "";
	private $configuration = array();
	protected $itemsClass = FALSE;//used by DisplayAnythingGalleryField for multiple items handling
	
	public $resize_method = "CroppedImage";
	public $thumb_width = 120;
	public $thumb_height = 120;
	
	public function __construct($controller, $name, $sourceClass, $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = "") {
		parent::__construct($controller, $name, $sourceClass, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);
		$this->SetMimeTypes();
		$this->LoadAdminCSS();
	}
	
	protected function LoadAdminCSS() {
		Requirements::css("display_anything/css/admin.css");
	}
	
	final private function ToBytes($str){
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
	 		UploadAnything requires your developer (or you) to provide it a map of allowed mimetype. Keys are mimetypes, values are a short blurb about the file. By default it uses the standard mimetypes for JPG, GIF and PNG files.
	 		If you are uploading a valid file and the UploadAnything MimeType checker is not allowing it, first determine it's mimetype and check against the whitelist you have provided. Some older software will save a file in older, non-standard formats.
	 		UploadAnything uses the 'type' value provided by the PHP file upload handler for standard uploads that populate $_FILES. For XHR uploads, UploadAnything uses finfo_open() if available, followed by an image mimetype check, followed by mime_content_type (deprecated). If no mimeType can be detected. UploadAnything refuses the upload, which is better than allowing unknown files on your server.
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
	 * @note loads the correct handler depending on incoming data
	 */
	protected function LoadUploadHandler() {
		if (isset($_GET[$this->fileKey])) {
			$this->file = new UploadAnything_Upload_XHR($this->fileKey);
			$this->file->saveToTmp();//saves raw stream to tmp location
		} elseif (isset($_FILES[$this->fileKey])) {
			$this->file = new UploadAnything_Upload_Form($this->fileKey);
		} else {
			throw new Exception("No upload handler was defined for this request");
		}
	}
	
	/**
	 * SetUploadFileName()
	 * @param $uploadDirectory wherever uploades are saving at
	 * @param $overwrite if TRUE will overwrite the current same-named file in that directory
	 */
	protected function SetUploadFileName($uploadDirectory, $overwrite = FALSE) {
		$pathinfo = pathinfo($this->file->getName());
		$filename = $pathinfo['filename'];
		$ext = $pathinfo['extension'];
		if(!$overwrite && file_exists($uploadDirectory . "/" . $filename . "." . $ext)) {
			while (file_exists($uploadDirectory . "/" . $filename . "." . $ext)) {
				//while the file exists, prepend a suffix to the file name
				$filename = "___" . rand(0, 99) . "_" . $filename;
			}
		}
		$this->upload_file_name = str_replace(" ", "_", $filename . "." . $ext);
		return $uploadDirectory . "/" . $this->upload_file_name;
	}
	
	public function GetFileName() {
		return $this->upload_file_name;
	}
	
	final private function CheckAllowedSize() {
		$size = $this->file->getSize();
		
		if ($size == 0) {
			throw new Exception('File is empty');
		}
		
		$postSize = $this->ToBytes(ini_get('post_max_size'));
		$uploadSize = $this->ToBytes(ini_get('upload_max_filesize'));
		$msize = ($size / 1024 / 1024) . 'M';
		if ($size > $postSize) {
			throw new Exception("The server does not allow files of this size to be uploaded ({$msize}). Size Error #1.");
		}
		
		if ($size > $uploadSize) {
			throw new Exception("The server does not allow files of this size to be uploaded ({$msize}). Size Error #2.");
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
			$allowed_list .= $type . " (" .strtoupper($ext) . "), ";
		}
		
		$mimeType = $this->file->getMimeType();
		if(!array_key_exists($mimeType, $this->allowed_file_types)) {
			throw new Exception("The server does not allow files of type '{$mimeType}' to be uploaded. Allowed types: " .  trim($allowed_list, ", "));
		}
		return TRUE;
	}
	
	public function ShowReturnValue() {
		print htmlspecialchars(json_encode($this->returnValue), ENT_NOQUOTES);
	}
	
	public function GetReturnValue() {
		return $this->returnValue;
	}
	
	public function Success() {
		return !empty($this->returnValue['success']);
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
	 *					'sizeLimit' => 0, //ignored - server settings used instead
	 *					'minSizeLimit' => 0, //ignored
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
	
	public function ReplaceFile($replace = FALSE) {
		$this->replace_file = $replace;
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
	 */
	protected function CanUpload() {
		$member = Member::currentUser();
		if(empty($member->ID)) {
			throw new Exception("You must be signed in to the administration area to upload files");
		}
		return $member;
	}
	
	private function UnlinkFile($target) {
		if(is_writable($target)) {
			unlink($target);
		}
	}
	
	/**
	 * LoadScript()
	 * @note override this method to use your own CSS. Changing this may break file upload layout.
	 */
	protected function LoadScript() {
		
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
	protected function LoadCSS() {
		Requirements::css("display_anything/css/display.css");
		Requirements::css("display_anything/javascript/file-uploader/client/fileuploader.css");
	}
	
	/**
	 * load_jquery()
	 * @note override this method to use your own jquery lib file. You should provide a recent version or uploads may fail.
	 */
	protected function LoadAssets() {
		$this->LoadScript();
		$this->LoadCSS();
	}
	
	/**
	 * GetUploaderConfiguration()
	 * @note you can override this for custom upload configuration
	 */
	public function GetUploaderConfiguration() {
		$id = $this->id();
		$js = array();
		
		//work out the upload location.
		$this->configuration['action'] = $this->Link('Upload');
		$this->configuration['reload'] = $this->Link('ReloadList');
		
		if(!isset($this->configuration['params'])) {
			$this->configuration['params'] = array();
		}
		$this->configuration['params']['record'] = $this->GetAssociatedRecord();
		$string = htmlentities(json_encode($this->configuration), ENT_QUOTES, "UTF-8");
		//print $string;
		return $string;
	}
	
	/**
	 * GetAssociatedRecord()
	 * @todo support PK's other than ID
	 * @note can override this if you wish
	 */
	private function GetAssociatedRecord() {
		$list =  array();
		$list['ID'] = $this->controller->ID;
		return array(
			'd' => get_class($this->controller),
			'p' => $list,
		);
	}
	
	public function FieldPrefix() {
		return "";
	}
	
	public function FieldSuffix() {
		$list = $this->GetFileList();
		if(empty($list)) {
			$list = "<div class=\"file-uploader-item\"><p>No files have been associated yet...</p></div>";
		}
		$html = "<div class=\"file-uploader-list\">{$list}</div>";
		$html .= "<div class=\"help\"><div class=\"inner\">"
				. " <h4>Upload help</h4><ul>"
				. " <li><strong>Chrome</strong>, <strong>Safari</strong> and <strong>Firefox</strong> support multiple image upload (Hint: 'Ctrl/Cmd + click' to select multiple images in your file chooser)</li>"
				. "<li>In <strong>Firefox</strong>, Safari and <strong>Chrome</strong> you can drag and drop images onto the upload button</li>"
				. "<li>Internet Explorer does not support multiple file uploads or drag and drop of files.</li>"
				. "</ul>"
				. "</div></div>";
		return $html;
	}
	
	
	protected function FileEditorLink($file, $relation, $action = "edit") {
		switch($relation) {
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
	 * Editlink
	 * @note in gallery mode /admin/EditForm/field/ImageGallery/item/1/ DetailForm/field/GalleryItems/item/78/edit?SecurityID=xyz
	 * @note in single mode /admin/EditForm/field/FeatureImage/item/80/edit?SecurityID=xyz
	 * DeleteLink
	 * @note in gallery mode: /admin/EditForm/field/ImageGallery/item/1/DetailForm/field/GalleryItems/item/78/delete?SecurityID=xyz
	 * @note in single mode /admin/EditForm/field/FeatureImage/item/80/delete?SecurityID=xyz
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
		
		if($is_image) {
			$tag = $file->Thumbnail($this->resize_method, $this->thumb_width, $this->thumb_height);
			if($tag) {
				$thumb = $tag;
			} else {
				$thumb = "<img src=\"" . SAPPHIRE_DIR . "/images/app_icons/image_32.gif\" width=\"24\" height=\"32\" alt=\"unknown image\" /><br />(no thumbnail)";
			}
		} else {
			//get a file icon...
			$thumb = "<img src=\"" . SAPPHIRE_DIR . "/images/app_icons/generic_32.gif\" width=\"24\" height=\"32\" alt=\"file icon\" />";
		}
		
		$html .= "<div class=\"thumb\">{$thumb}</div>";
		$html .= "<div class=\"caption\"><p>" . substr($file->Title, 0, 16)  . "</p></div>";
		$html .= "</a>";
		$html .= "<div class=\"tools\">";
		$html .= "<a class=\"deletelink\" href=\"{$deletelink}\"><img src=\"/" . CMS_DIR . "/images/delete.gif\" alt=\"delete\" /></a>";
		$html .= "<img src=\"/display_anything/images/sort.png\" title=\"drag and drop to sort\" alt=\"drag and drop to sort\" /></a>";
		$html .= "</div>";
		$html .= "</div>";
		
		return $html;
	}
	
	/**
	 * FileList()
	 * @note returns an HTML file list. If the method 'UploadAnythingFileList' is implemented in the related dataobject, that is used and must return a string (i.e you author the list), if not, it's rendered as a list of inline items
	 * @return string
	 * @todo is "ID" ok to be hardcoded
	 */
	private function GetFileList() {
		$html = "";
		//TODO: has_one and has_many support
		if(method_exists('UploadAnythingFileList', $this->controller)) {
			return $this->$this->controller->UploadAnythingFileList();
		} else {
			$relation = $this->DataTypeRelation();
			switch($relation) {
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
	 * SILVERSTRIPE SPECIFIC @FormField overrides
	 */

	/**
	 * Field()
	 * @note just returns the field. Note that the FileUploader.js handles all the HTML machinations, we just provide a container
	 * @note to invoke the uploader, just add jQuery('
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
	
	function FieldHolder() {
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
		$titleBlock = (!empty($Title)) ? "<label class=\"left\" for=\"{$this->id()}\">$Title - <strong><a href=\"{$reload}\" class=\"reload\">reload</a></strong><a class=\"sortlink\" href=\"{$resort}\">sort</a></label>" : "";
		
		// $MessageType is also used in {@link extraClass()} with a "holder-" prefix
		$messageBlock = (!empty($Message)) ? "<span class=\"message $MessageType\">$Message</span>" : "";
		
		return <<<HTML
<div class="file-uploader">
	<div id="$Name" class="field $Type $extraClass">
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
	
	final private function IsSiteTree($class) {
		try {
			$reflect = new ReflectionClass('SiteTree');
			$instance  = new $class;
			return $reflect->isInstance($arg);
		} catch (Exception $e) {
			return FALSE;
		}
	}
	
	/**
	* Upload
	* @note handles a file upload to $uploadDirectory
	* @note saves the file and links it to the dataobject, returning the saved file ID
	* @todo handle has_one and has_many
	*/
	final public function Upload() {
		try {
		
			if(empty($_GET['record'])) {
				throw new Exception("The gallery has not been configured correctly.");
			}
			
			if(empty($_GET['record']['d'])) {
				throw new Exception("The related dataobject name was not provided.");
			}
			 
			if(empty($_GET['record']['p']) || !is_array($_GET['record']['p'])) {
				throw new Exception("The related dataobject primary key fields were not provided. Upload failed. Hint: use AssociateWith() durng field setup");
			}
			
			/**
			//set the related dataobject
			$conditional = array();
			foreach($_GET['record']['p'] as $column=>$value) {
				$conditional[] = "\"" . Convert::raw2sql($_GET['record']['d']) . "\".\"" . Convert::raw2sql($column) . "\" = '" . Convert::raw2sql($value) . "'";
			}
			
			//upload association target
			$dataobject = FALSE;
			try {
			
				if($this->IsSiteTree($_GET['record']['d'])) {
					$target = "SiteTree";
				} else {
					$target = $_GET['record']['d']
				}
			
				$dataobject = DataObject::get_one($target, implode(" AND ", $conditional));
			} catch (Exception $e) {
				throw new Exception("Query failed with error: " . $e->getMessage());
			}
			
			if(!$dataobject || empty($dataobject)) {
				throw new Exception("The related dataobject is not valid. This can happen if the gallery type has changed. Upload failed.");
			}
			
			*/
			
			//current member if they can upload
			$member = $this->CanUpload();
		
			//set this->file to the correct handler
			$this->LoadUploadHandler();
			
			//if not set, create a target location
			if($this->target_location == "") {
				$this->target_location = "Uploads";
			}
		
			//final location of file
			$targetDirectory = "/" . trim($this->target_location, "/ ");
			$uploadDirectory = "/" . trim(ASSETS_PATH, "/ ") . $targetDirectory;
			
			//print $uploadDirectory;exit;
		
			if (!is_writable($uploadDirectory)){
				throw new Exception("Server error. Upload directory '{$uploadDirectory}' isn't writable.");
			}
			
			if (!$this->file){
				throw new Exception('No file handler was defined for this upload.');
			}
			
			$this->CheckAllowedType();
			
			$this->CheckAllowedSize();
			
			//now save the file
			$target = $this->SetUploadFileName($uploadDirectory, $this->replace_file);
			
			//saves the file to the target directory
			$this->file->save($target);
			
			//here ? then the file save to disk has worked
			
			//make a folder record (optionally makes it on the file system as well, although this is done in this->file->save()
			$folder = Folder::findOrMake($targetDirectory);//without ASSETS_PATH !
			if(empty($folder->ID)) {
				$this->UnlinkFile($target);
				throw new Exception('No folder could be assigned to this file');
			}
			
			try {
				$filename =  $this->GetFileName();
			
				$relation = $this->DataTypeRelation();
				switch($relation) {
					case "single";
						
						$file = new UploadAnythingFile();//TODO - this should match the file type provided
						$file->Name = $filename;
						$file->Title = $filename;
						$file->Filename = $filename;
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
							$file->Filename = $filename;
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
		
		$this->ShowReturnValue();
		
	}
	
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
	 * @note HTTP POST API to update sort order in a gallery
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
	
	final private function DataTypeRelation() {
		if($this->controller->has_one($this->name)) {
			if($this->itemsClass) {
				if($this->controller->{$this->name}()->has_many($this->itemsClass)) {
					return "gallery";
				} else {
					throw new Exception("Controller '" . get_class($this->controller) . "' has one '{$this->name}' but '{$this->name}' does not have many '{$this->itemsClass}'");
				}
			} else {
				return "single";
			}
		}
		
		throw new Exception("The datatype relation between '{$this->name}' and '" . get_class($this->controller) . "' is neither has_one or has_many. The file could not be saved");
	}
}


class UploadAnythingField_Popup extends ComplexTableField_Popup {
	function __construct($controller, $name, $fields, $validator, $readonly, $dataObject) {
		parent::__construct($controller, $name, $fields, $validator, $readonly, $dataObject);
		
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
	private $filePermission = 0644;
	
	public function __construct($fileKey) {
		$this->fileKey = $fileKey;
	}
	
	public function SetFilePermission($mode = 0644) {
		$this->filePermission = $mode;
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
			@chmod($path, $this->filePermission);
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
	
	public function __construct($fileKey) {
		$this->fileKey = $fileKey;
	}
	
	/**
	* Save the file to the specified path
	* @return boolean TRUE on success
	*/
	function save($path) {
		if(!is_uploaded_file($_FILES[$this->fileKey]['tmp_name'])) {
			throw new Exception("The server did not allowed this file to be uploaded.");
		}
		if(!move_uploaded_file($_FILES[$this->fileKey]['tmp_name'], $path)){
			throw new Exception('Could not save uploaded file. Can the destination path be written to?');
		}
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