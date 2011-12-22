/**
 * UploadAnything - javascript behavours for image uploader
 * @copyright Codem 2011
 * @see license.txt
 */
var UploadAnything = function() {};
UploadAnything.prototype = {
	debug : false,
	init: function() {},
	clog : function(line) {
		if(typeof console.log != 'undefined') {
			console.log(line);
		}
	},
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
	
	//load up in a full screen greybox
	load_lightbox : function(elem) {
		try {
			var _self = this;
			var href = jQuery(elem).attr('href');
			var title = jQuery(elem).attr('title');
			if(title == '') {
				title = 'Un-named file';
			}
			GB_showFullScreen(
				title,
				href,
				function() {
					//closing
					jQuery(elem).parents('.file-uploader').find('a.reload').trigger('click');
				}
			);
		} catch (e) {
			return false;
		}
	},
	viewer : function() {
		var _self = this;
		
		//drag and drop sorting of items
		jQuery('.file-uploader-list')
			.sortable({
				stop : function() {
					var items=[];
					jQuery(this).children('.file-uploader-item').each(
						function(k,v) {
							items[k] = {
								id : jQuery(this).attr('rel'),
								pos : k,	
							}
						}
					);
					
					var list = this;
					jQuery.post(
						jQuery(list).parents('.file-uploader').find('a.sortlink').attr('href'),
						{ items : items },
						function() {
							jQuery(list).parents('.file-uploader').find('a.reload').trigger('click');
						}
					);
				}
			}).disableSelection();
	
		//editing items, launch lightbox
		jQuery('.file-uploader-list .file-uploader-item a.editlink')
			.live(
				'click',
				function(event) {
					event.preventDefault();
					_self.load_lightbox(this);
				}
			);
		
		//reload items
		jQuery('.file-uploader a.reload-all').click(
			function() {
				jQuery(this).parents('.file-uploader').find('.qq-upload-list').hide().empty();
			}
		);
		
		jQuery('.file-uploader a.reload')
			.live(
				'click',
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
		
		//delete items
		jQuery('.file-uploader-list .file-uploader-item a.deletelink')
			.live(
				'click',
				function(event) {
					event.preventDefault();
					try {
						var _self = this;
						jQuery.post(
							jQuery(this).attr('href'),
							{},
							function() {
								jQuery(_self).parents('.file-uploader').find('a.reload').trigger('click');
							}
						);
					} catch(e) {
						alert(e);
					}
				}
			);
			
		//edit usage
		jQuery('.display_anything_usage').find('select.usage_picker').change(
			function() {
				var p = jQuery(this).parents('.display_anything_usage');
				var o = jQuery(this).children('option:selected');
				var t = o.text();
				var title = '';
				var id = '';
				var mimetypes = '';
				if(jQuery(this).val() != '') {
					var patt = /(.*)\s{1}\((.*)\)/;
					var matches = t.match(patt);
					if(typeof matches[1] != 'undefined') {
						title = matches[1];
					}
					if(typeof matches[2] != 'undefined') {
						mimetypes = matches[2];
					}
					id = o.attr('value');
				}
				p.find('input.usage_id').val(id);
				p.find('input.usage_title').val(title);
				p.find('textarea.usage_mimetypes').val(mimetypes);
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

if(jQuery('#Form_EditForm').length == 0) {
	//handle when loaded within greybox lightbox
	jQuery(document).ready(
		function() {
			var u = new UploadAnything();
			u.init();
			u.queue_all();
			u.viewer();
		}
	);
}