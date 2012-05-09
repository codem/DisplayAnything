<?php
/**
 * SingleImagePage
* @note this is an example Single Image Gallery page, it contains one UploadAnythingFile relation. It's worth noting that Silverstripes built in FileField would suffice here, unless you want drag and drop ;)
 */
class SingleImagePage extends Page {

	public static $has_one = array(
		'SingleFile' => 'UploadAnythingFile',
	);
	
	public function getCmsFields() {
		$fields = parent::getCmsFields();
		//SINGLE field - with a test to see if the page has been saved
		//NOTE: that single field uploads can be done with the core Silverstripe FileField (and is probably more stable at this point)
		if(!empty($this->ID)) {
			$uploader = new UploadAnythingField($this, 'SingleFile','File');
			$uploader->SetMimeTypes(array('text/plain'));//this single file uploader only allows plain text uploads
		} else {
			$uploader = new LiteralField("PageNotSavedYet", "<p>The file may be uploaded after saving this page.</p>");
		}
		$fields->addFieldToTab('Root.Content.Image', $uploader);
		return $fields;
	}
}


class SingleImagePage_Controller extends Page_Controller {

}
?>