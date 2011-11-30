/**
 * UploadAnything - javascript behavours for image uploader
 */
var UploadAnything = function() {};
UploadAnything.prototype = {
	debug : false,
	init: function() {},
	queue : function(id, upload_config) {
		upload_config.element = jQuery('#' + id)[0];
		upload_config.debug = this.debug;
		upload_config.onSubmit = function(id, fileName) {
			jQuery(this.element).find('.qq-upload-list').show();
		};
		upload_config.onComplete = function(id, fileName, responseJSON) {
			jQuery(this.element).parents('.file-uploader:first').find('a.reload').trigger('click');
		};
		var u = new qq.FileUploader(upload_config);
	},
	queue_all : function() {
		var _self = this;
		jQuery('.uploadanything-upload-box').each(
			function() {
				try {
					//upload config comes in from rel attribute
					var config = jQuery.parseJSON(jQuery(this).attr('rel'));
					_self.queue(jQuery(this).attr('id'), config);
				} catch(e) {
					jQuery(this).html('Could not configure the uploader.');
				}
			}
		);
	},
	viewer : function() {
		jQuery('.file-uploader-list')
			.sortable({
				stop : function() {
					var _self = this;
					var items=[];
					jQuery(this).children('.file-uploader-item').each(
						function(k,v) {
							items[k] = {
								id : jQuery(this).attr('rel'),
								pos : k,	
							}
						}
					);
					
					jQuery.post(
						jQuery(this).parents('.file-uploader').find('a.sortlink').attr('href'),
						{ items : items },
						function() {
							jQuery(_self).parents('.file-uploader').find('a.reload').trigger('click');
						}
					);
				}
			}).disableSelection();
	
		jQuery('.file-uploader-list .file-uploader-item a.editlink')
			.click(
				function(event) {
				
					var _self = this;
					
					event.preventDefault();
					
					var _self = this;
					var href = jQuery(this).attr('href');
					var title = jQuery(this).attr('title');
					if(title == '') {
						title = 'Un-named file';
					}
					
					GB_showFullScreen(
						title,
						href,
						function() {
							//closing
							jQuery(_self).parents('.file-uploader').find('a.reload').trigger('click');
						}
					);
				}
			);
		
		jQuery('.file-uploader a.reload')
			.click(
				function(event) {
					event.preventDefault();
					jQuery('.qq-upload-drop-area').hide(100);
					jQuery(this)
						.addClass('loading')
						.parents('.file-uploader')
						.find('.file-uploader-list')
						.fadeTo(200,0.2)
						.load(
							jQuery(this).attr('href'),
							{},
							function() {
								jQuery(this).fadeTo(200,1);
								jQuery('.file-uploader a.reload').removeClass('loading');
							}
						);
				}
			);
		
		jQuery('.file-uploader-list .file-uploader-item a.deletelink')
			.click(
				function(event) {
					event.preventDefault();
					var _self = this;
					jQuery.post(
						jQuery(this).attr('href'),
						{},
						function() {
							jQuery(_self).parents('.file-uploader').find('a.reload').trigger('click');
						}
					);
				}
			);
	}
};
Behaviour.register({
	'#Form_EditForm' : {
		initialize : function() {
			this.observeMethod('PageLoaded', this.pageLoaded);
			this.observeMethod('BeforeSave', this.beforeSave);
			this.pageLoaded(); // call pageload initially too.
		},
		
		pageLoaded : function() {
			var u = new UploadAnything();
			u.init();
			u.queue_all();
			u.viewer();
		},
		
		beforeSave: function() {
		}
	} // #Form_EditForm
});