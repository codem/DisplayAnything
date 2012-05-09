<?php
/**
 * ImageGalleryPage
 * @note this is an example Image Gallery page, it contains one DisplayAnythingGallery relation
 */
class ImageGalleryPage extends Page {

	public static $has_one = array(
		'ImageGallery' => 'DisplayAnythingGallery',
	);
	
	public function getCmsFields() {
		$fields = parent::getCmsFields();

		//GALLERY per page
		$gallery = new DisplayAnythingGalleryField(
			$this,
			'ImageGallery',
			'DisplayAnythingGallery'
		);
		$gallery->SetTargetLocation('/image_gallery');//relative to ASSETS_PATH
		$fields->addFieldToTab('Root.Content.Gallery', $gallery);
		
		return $fields;
	}
}


class ImageGalleryPage_Controller extends Page_Controller {

}
?>