<?php
/**
 * DisplayAnythingGalleryField()
 * @note provides specific gallery rendering for a DisplayAnythingGallery
 */
class DisplayAnythingGalleryField extends UploadAnythingField {
	
	protected $itemsClass;
	
	protected $detect_image_gallery_module = TRUE;
	
	public function __construct($controller, $name, $sourceClass, $itemsClass = "GalleryItems", $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = "") {
		parent::__construct($controller, $name, $sourceClass, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);
		
		$this->itemsClass = $itemsClass;
		
		$this->SetMimeTypes();
	}

	/**
	 * ImageGalleryAlbums()
	 * @note gets ImageGalleryAlbum records for the current page
	 * @note we don't use the ORM here as the image_gallery module may no longer exist in the code base
	 */
	protected function ImageGalleryAlbums() {
		$list = array();
		if($id = $this->controller->ID) {
			$sql = "SELECT a.*, COUNT(i.ID) AS ItemCount FROM ImageGalleryAlbum a"
				. " LEFT JOIN ImageGalleryItem i ON i.AlbumID = a.ID"
				. " WHERE a.ImageGalleryPageID = {$id}";
			if($results = DB::Query($sql)) {
				foreach($results as $record) {
					if(!empty($record['ID'])) {
						$list[$record['ID']] = "  " . $record['AlbumName'] . " - {$record['ItemCount']} image(s)";
					}
				}
				return $list;
			}
		}
		return $list;
	}
	
	/**
	 * ImageGalleryAlbum()
	 * @note gets an ImageGalleryAlbum record
	 * @note we don't use the ORM here as the image_gallery module may no longer exist in the code base
	 */
	protected function ImageGalleryAlbum($id) {
		if($results = DB::Query("SELECT a.* FROM ImageGalleryAlbum a WHERE a.ID = {$id}")) {
			foreach($results as $record) {
				return $record;
			}
		}
		return FALSE;
	}
	
	/**
	 * ImageGalleryAlbumItems()
	 * @note gets ImageGalleryItems for an ImageGalleryAlbum record
	 * @note we don't use the ORM here as the image_gallery module may no longer exist in the code base
	 */
	protected function ImageGalleryAlbumItems($album_id) {
		$items = array();
		if($results = DB::Query("SELECT i.* FROM ImageGalleryItem i WHERE i.AlbumID = {$album_id}")) {
			foreach($results as $record) {
				$items[] = $record;
			}
		}
		return $items;
	}
	
	public function FieldHolder() {
		$html = "<div class=\"display_anything_field upload_anything_field\">";
		
		$id = $this->controller->{$this->name}()->getField('ID');
		
		$migrated_value = $this->controller->{$this->name}()->getField('Migrated');
		
		$migrator = FALSE;
		if($this->detect_image_gallery_module && $migrated_value == 0) {
			//display only if we want to detect imagegallery albums and it's not already migrated
			$list = $this->ImageGalleryAlbums();
			if(!empty($list)) {
				$migrator = TRUE;
				$html .= "<div class=\"field_content migrate\">";
				$html .= "<fieldset><h5>Display Anything has detected an ImageGallery album associated with this page</h5>";
				$html .= "<p>Do you wish to migrate it to your new gallery?<p>";
				$html .= "<p>Migration notes:</p><ul>";
				$html .= "<li>The original ImageGallery album will remain untouched.</li>";
				$html .= "<li>Files will be copied alongside current files, this will allow you to remove the old gallery as and when required.</li>";
				$html .= "</ul>";
				$migrate = new DropDownField("{$this->name}[{$id}][MigrateImageGalleryAlbumID]","Choose an album to migrate images from", $list, '', NULL, '[Do not migrate]');
				$html .= $migrate->FieldHolder();
				$html .= "</fieldset>";
				$html .= "</div>";
			}
		} else if ($migrated_value == 1) {
			$migrator = TRUE;
		}
		
		$html .= "<div class=\"field_content\">";
		
		$html .= "<fieldset><h5>Gallery settings and options</h5>";
		
		$title = new TextField("{$this->name}[{$id}][Title]","Title", $this->controller->{$this->name}()->getField('Title'));
		$html .= $title->FieldHolder();
		
		$description = new TextareaField("{$this->name}[{$id}][Description]","Description", 3, NULL, $this->controller->{$this->name}()->getField('Description'));
		$html .= $description->FieldHolder();
		
		$visible = new CheckboxField("{$this->name}[{$id}][Visible]","Publicly Visible", $this->controller->{$this->name}()->getField('Visible') == 1 ?  TRUE : FALSE);
		$html .= $visible->FieldHolder();
		
		
		if($migrator && $migrated_value == 1) {
			//only need to show this post migration
			$migrated = new CheckboxField("{$this->name}[{$id}][Migrated]","Image Gallery migration complete (uncheck and save to display migration options)", TRUE);
			$html .= $migrated->FieldHolder();
		}
		
		$html .= "</fieldset></div>";
		
		$html .= "<div class=\"field_content\">";
		
		$html .= "<fieldset><h5>Gallery Items</h5>";
		
		if(!empty($id)) {
			$html .= parent::FieldHolder();
		} else {
			$html .= "<div class=\"message\"><p>Gallery items can be uploaded after the gallery is saved for the first time</p></div>";
		}
		
		$html .= "</fieldset></div>";
		
		$html .= "</div>";
		return $html;
	}
	
	public function saveInto(DataObject $record) {
	
		//save into the DisplayAnythingGallery
		if(!empty($_POST[$this->name]) && is_array($_POST[$this->name])) {
			$gallery = $record->{$this->name}();
			$migrate = FALSE;
			foreach($_POST[$this->name] as $id=>$data) {
			
				if(!empty($data['MigrateImageGalleryAlbumID'])) {
					$migrate = $data['MigrateImageGalleryAlbumID'];
				}
				
				if($id == 0 || $id == $gallery->ID) {
					//creating this gallery or updating it...
					$gallery->Title = !empty($data['Title']) ? $data['Title'] : '';	
					$gallery->Visible = !empty($data['Visible']) ?  1 : 0;
					$gallery->Migrated = !empty($data['Migrated']) ?  1 : 0;
					if($id = $gallery->write()) {
						$relation_field = $this->name . "ID";
						$record->$relation_field = $id;
					} else {
						throw new Exception("Could not save gallery '{$gallery->Title}'");
					}
					break;
				}
			}
			
			if($migrate && $gallery) {
				$this->MigrateImageGalleryAlbum($migrate, $gallery);
			}
		}
	}
	
	protected function MigrateImageGalleryAlbum($id, $gallery) {
		try {
			//grab this album
			$album = $this->ImageGalleryAlbum($id);
			
			if(empty($album['ID'])) {
				throw new Exception("The target album does not exist");
			}
			
			//grab its items
			$items = $this->ImageGalleryAlbumItems($album['ID']);
			
			if(empty($gallery->ID)) {
				throw new Exception("I can't migrate an album {$album->AlbumName} into an empty gallery");
			}
			
			if(empty($gallery->Title)) {
				$gallery->Title = $album['AlbumName'];
			}
			
			if(empty($gallery->Description)) {
				$gallery->Description = $album['Description'];
			}
			
			$gallery->Migrated = 1;
			
			$gallery->write();
			
			if(!empty($items)) {
				foreach($items as $item) {
				
					//get the source image for this item
					$image = DataObject::get_by_id('File', $item['ImageID']);
					if(!empty($image->ID)) {
					
						//does the image exist ?
						$source_filename_path = BASE_PATH . "/"  . $image->Filename;
						
						$target_filename = $target_filename_path = FALSE;
						$path_info = pathinfo($source_filename_path);
						if(!empty($path_info['dirname'])
							&& !empty($path_info['basename'])) {
								$target_filename = "DA_copy_of_" . $path_info['basename'];
								$target_filename_path = $path_info['dirname'] . "/" . $target_filename;
						}
						
						//print $source_filename_path . "\n";print $target_filename . "\n";print $target_filename_path . "\n";
						
						//we'll make a copy of it so that the old images can be deleted without touching the new files
						//if the target image exists, assume it's already been migrated and just update the record
						$migrated_file = FALSE;
						if(file_exists($target_filename_path)) {
							$copy = TRUE;
							//grab the file_id. this is an update
							$pattern = preg_quote(addslashes(BASE_PATH . "/"));
							$target_replaced = preg_replace("|^{$pattern}|", "", $target_filename_path);
							$migrated_file = DataObject::get_one("File", "Filename='" . convert::raw2sql(ltrim($target_replaced,"/")) . "'");
							
						} else if(is_readable($source_filename_path)
							&& is_readable(dirname($target_filename_path))
							&& !file_exists($target_filename_path)
							&& is_writable(dirname($target_filename_path))) {
								$copy = copy($source_filename_path, $target_filename_path);
						}
						
						if($copy) {
							$file = new DisplayAnythingFile;
							$file->Visible = 1;
							$file->Caption = $item['Caption'];
							$file->GalleryID = $gallery->ID;
							$file->Filename = $target_filename_path;
							$file->ParentID = $image->ParentID;
							$file->OwnerID = $image->OwnerID;
							$file->Sort = $image->Sort;
							$file->Title = $image->Title;
							if(!empty($migrated_file->ID)) {
								/**
								 * an update
								 * note if the file already exists on the file system
								 * but not in the DB, a new file will be created
								 */
								$file->ID = $migrated_file->ID;
							}
							//don't set ->Name, crazy crap happens thanks to File::setName(0
							$file_id = $file->write();
						}
						
					}
				}
			}
			
		} catch (Exception  $e) {
			//failed
		}
	}
}
?>