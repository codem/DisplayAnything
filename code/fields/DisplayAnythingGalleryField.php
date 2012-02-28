<?php
/**
 * DisplayAnythingGalleryField()
 * @note provides a gallery conifigration and viewer field in the CMS for a DisplayAnythingGallery
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
	 * @note this will return an empty list if
	 * 			1. There are no ImageGalleryAlbum or ImageGalleryItem tables in the database
	 * 			2. There are but no Albums are related to the current page
	 * 			3. There is no current page (the controller->ID)
	 * 			Rather than show an error, the CMS tab should not show at all as it would be irritating for those who are not doing migrations
	 * @returns array
	 */
	protected function ImageGalleryAlbums() {
		$list = array();
		
		if($id = $this->controller->ID) {
			$sql = "SELECT a.*, COUNT(i.ID) AS ItemCount FROM ImageGalleryAlbum a"
				. " LEFT JOIN ImageGalleryItem i ON i.AlbumID = a.ID"
				. " WHERE a.ImageGalleryPageID = {$id}";
			$results = DB::Query($sql, FALSE);
			if(!$results || !$results->valid()) {
				//just return an empty list so as not to show the migration tab
				return array();
			}
			foreach($results as $record) {
				if(!empty($record['ID'])) {
					$list[$record['ID']] = "  " . $record['AlbumName'] . " - {$record['ItemCount']} image(s)";
				}
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
	
		$fields = new Fieldset(array(new TabSet('Root')));
		$fields->addFieldToTab('Root', new Tab($this->name .'Files', 'Files'));
		$fields->addFieldToTab('Root', new Tab($this->name .'Details', 'Details'));
		$fields->addFieldToTab('Root', new Tab($this->name .'Usage', 'Usage'));
		
		$id = $this->controller->{$this->name}()->getField('ID');
		
		//MIGRATION TAB
		$migrated_value = $this->controller->{$this->name}()->getField('Migrated');
		if($this->detect_image_gallery_module) {
			if($migrated_value == 0) {
				//display only if we want to detect imagegallery albums and it's not already migrated
				$list = $this->ImageGalleryAlbums();
				if(!empty($list)) {
					$fields->addFieldToTab('Root', new Tab('ImageGalleryMigration'));
					$fields->addFieldsToTab(
						'Root.ImageGalleryMigration',
						array(
							new LiteralField('ImageGalleryMigrationMessagePrefix',
								"<div class=\"field_content display_anything display_anything_migrate\">"
								. "<fieldset><h5>Display Anything has detected an ImageGallery album associated with this page</h5>"),
							new DropDownField("{$this->name}[{$id}][MigrateImageGalleryAlbumID]","Choose an album to migrate images from", $list, '', NULL, '[Do not migrate]'),
							new LiteralField('ImageGalleryMigrationMessageSuffix', "<h5>Migration notes</h5><ul>"
									. "<li>The original ImageGallery album will remain untouched.</li>"
									. "<li>You can migrate files as many times as you like</li>"
									. "<li>Files will be copied rather than moved. This will allow you to remove the old gallery as and when required.</li>"
									. "<li>If the ImageGalleryAlbum or ImageGalleryItem tables are removed from the database, this tab will no longer appear.</li>"
									. "</ul></fieldset></div>")
						)
					);
				}
			} else if ($migrated_value == 1) {
				$fields->addFieldToTab('Root', new Tab('ImageGalleryMigration'));
				$fields->addFieldsToTab(
					'Root.ImageGalleryMigration',
					array(
						new LiteralField("ImageGalleryMigrationMessagePrefix", "<div class=\"field_content display_anything_migrate display_anything\"><h5>Complete</h5>"),
						new CheckboxField("{$this->name}[{$id}][Migrated]","Image Gallery migration complete (uncheck and save to display migration options or if you wish to sync files again.)", TRUE),
						new LiteralField("ImageGalleryMigrationMessageSuffix", "</div>"),
					)
				);
			}
		}
		//END MIGRATION TAB
		

		//START OLD
		
		$fields->addFieldsToTab(
			"Root.{$this->name}Details",
			array(
				new TextField("{$this->name}[{$id}][Title]","Title", $this->controller->{$this->name}()->getField('Title')),
				new TextareaField("{$this->name}[{$id}][Description]","Description", 3, NULL, $this->controller->{$this->name}()->getField('Description')),
				new CheckboxField("{$this->name}[{$id}][Visible]","Publicly Visible", $this->controller->{$this->name}()->getField('Visible') == 1 ?  TRUE : FALSE),
			)
		);
		
		$picker = new DropDownField("{$this->name}[{$id}][UsageID]","", DataObject::get('DisplayAnythingGalleryUsage')->map('ID','TitleMap'), $this->controller->{$this->name}()->getField('UsageID'), NULL, '');
		$picker->addExtraClass('usage_picker');
		
		$usage_id = new HiddenField("GalleryUsage[{$this->name}][{$id}][ID]");
		$usage_id->addExtraClass('usage_id');
		$usage_title = new TextField("GalleryUsage[{$this->name}][{$id}][Title]","Title");
		$usage_title->addExtraClass('usage_title');
		$usage_mimetypes = new TextareaField("GalleryUsage[{$this->name}][{$id}][MimeTypes]","Allowed Mimetypes", 3, NULL);
		$usage_mimetypes->addExtraClass('usage_mimetypes');
		
		$fields->addFieldsToTab(
			"Root.{$this->name}Usage",
			array(
				new LiteralField("GalleryUsageBoxStart", "<div class=\"display_anything display_anything_usage\">"),
				new HeaderField("GalleryUsagePicker","Pick a gallery usage", 5),
				$picker,
				new HeaderField("GalleryUsageEntry","Enter new usage or choose a current one to edit", 5),
				$usage_id, $usage_title, $usage_mimetypes,
				new LiteralField("GalleryUsageBoxEnd", "</div>"),
			)
		);
		
		//the actual gallery field, using the parent field to render
		$html = "<div class=\"display_anything_field upload_anything_field\">";
		//this container is used to determine child-parent relationship in display.js
		if(!empty($id)) {
			$html .= parent::FieldHolder();
		} else {
			$html .= "<div class=\"message\"><p>Gallery items can be uploaded after the gallery is saved for the first time</p></div>";
		}
		$html .= "</div>";
		
		$fields->addFieldsToTab(
			"Root.{$this->name}Files",
			array(
				new LiteralField('DisplayAnythingGalleryField', $html)
			)
		);
		
		
		/**
		 * Finally, if the controller is a page and this gallery is shared among pages, allow the user to control
		 * which pages it appears on
		 * This occurs when the page is duplicated and the gallery should not be shared
		 */
		if($pages = $this->GetPages($id)) {
			if(count($pages) > 1) {
			
				$fields->addFieldToTab('Root', new Tab($this->name .'Pages', 'Pages'));
			
				//some duplicates.. show a tab
				$fields->addFieldsToTab(
					"Root.{$this->name}Pages",
					array(
						new LiteralField('SharedPagesInformation', "<p>This gallery is being shared by other pages on this site. Use the checkboxes below to control associated pages. Changes to this gallery will appear on all associated pages.</p>"),
						new CheckBoxSetField("SharedPages[{$this->name}][{$id}]", "", $pages, array_keys($pages)),
					)
				);
			}
		}
		
		
		$html = "";
		foreach($fields as $field) {
			$html .= $field->FieldHolder();
		}
		return $html;
	}
	
	/**
	 * ControllerIsSiteTree()
	 * @note determine if the current controller is an instance of SiteTree
	 * @return boolean
	 */
	protected function ControllerIsSiteTree() {
		return $this->controller instanceof SiteTree;
	}
	
	/**
	 * GetPages()
	 * @note if the controller is a SiteTree then return all pages associated with it
	 * @param $id the gallery id
	 * @param $raw if TRUE return as a DataObjectSet else return an array or boolean FALSE on error
	 * @todo the DataObject::get($class, $name = $id) needs to be looked at:
	 			1. what happens with multiple tables that contain the same column name ?
	 			2. using "`Page`.`{$name}` = " . $id assumes the gallery is attached to Page
	 			3. using "`{$class}`.`{$name}` = " . $id assumes that the controller class contains the column and not a parent class
	 */
	protected function GetPages($id, $raw = FALSE) {
		$list = FALSE;
		if($id > 0 && $this->ControllerIsSiteTree()) {
			$class = get_class($this->controller);
			$current = $this->controller->ID;
			$name = $this->name . "ID";
			$pages = DataObject::get($class, "`{$name}` = " . $id);
			//$pages = DataObject::get($class, "`{$class}`.`{$name}` = " . $id);
			if($pages) {
				if($raw) {
					return $pages;
				} else {
					foreach($pages as $page) {
						$list[$page->ID] = ($page->MenuTitle != "" ? $page->MenuTitle : $page->Title) . ($current == $page->ID ? " (current page)" : "");
					}
				}
			}
		}
		return $list;
	}
	
	/**
	 * SaveSharedPages()
	 * @note if the controller is a SiteTree, ensure that shared pages are saved correctly
	 * @note a gallery must be linked to at least one page. If they are competely unlinked then the current page will be associated with the gallery
	 * @return boolean
	 * @param $id the gallery id
	 */
	protected function SaveSharedPages($id) {
		if($this->ControllerIsSiteTree()) {
			$list = array();
			if($pages = $this->GetPages($id, TRUE)) {
			
				//the gallery must be saved against at least one page
				$savelist = (!empty($_POST['SharedPages'][$this->name][$id]) && is_array($_POST['SharedPages'][$this->name][$id]) ? $_POST['SharedPages'][$this->name][$id] : array($this->controller->ID));
				
				//cycle through current associated pages
				foreach($pages as $page) {
					//is this page NOT in the save list ?
					if(!in_array($page->ID, $savelist)) {
						//remove the association with this page
						$field = $this->name . "ID";
						$page->$field = 0;
						$page->write();
					}
				}
				
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * SaveUsage()
	 * @note saves gallery usage info
	 * @param $id the gallery id
	 * @return boolean
	 */
	protected function SaveUsage($id) {
		if(!empty($_POST['GalleryUsage'][$this->name][$id]['Title'])) {
			//adding a new gallery usage
			$usage = new DisplayAnythingGalleryUsage();
			$usage->Title = $_POST['GalleryUsage'][$this->name][$id]['Title'];
			$usage->MimeTypes = (!empty($_POST['GalleryUsage'][$this->name][$id]['MimeTypes']) ? $_POST['GalleryUsage'][$this->name][$id]['MimeTypes'] : '');
			
			if(!empty($_POST['GalleryUsage'][$this->name][$id]['ID'])) {
				$usage->ID = $_POST['GalleryUsage'][$this->name][$id]['ID'];
			}
			
			return $usage->write();
		}
		return FALSE;
	}
	
	public function saveInto(DataObject $record) {
	
		//save into the DisplayAnythingGallery
		if(!empty($_POST[$this->name]) && is_array($_POST[$this->name])) {
			$gallery = $record->{$this->name}();
			$migrate = FALSE;
			foreach($_POST[$this->name] as $id=>$data) {
			
				$this->SaveSharedPages($id);
			
				if($usage = $this->SaveUsage($id)) {
					$gallery->UsageID = $usage;
				} else if(!empty($data['UsageID'])) {
					$gallery->UsageID = $data['UsageID'];
				}
			
				if(!empty($data['MigrateImageGalleryAlbumID'])) {
					$migrate = $data['MigrateImageGalleryAlbumID'];
				}
				
				if($id == 0 || $id == $gallery->ID) {
					//creating this gallery or updating it...
					$gallery->Title = !empty($data['Title']) ? $data['Title'] : '';	
					$gallery->Description = !empty($data['Description']) ? $data['Description'] : '';	
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
