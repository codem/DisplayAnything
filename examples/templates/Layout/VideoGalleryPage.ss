<div>
	
	<h2>$Title</h2>

	$Content
	
	<% if VideoGallery %>
		<% control VideoGallery %>
			<div id="ImageGallery">
				<h4>$Title</h4>
				<div class="inner">
					<% if OrderedGalleryItems %>
						<div id="OrderedGalleryItems">
							<ul id="GalleryList">
								<% control OrderedGalleryItems %>
									<li class="$EvenOdd $FirstLast">
										$EmbedCode(500, 300)
									</li>
								<% end_control %>
							</ul>
						</div>
					<% end_if %>
					<% include Clearer %>
				</div>
			</div>
		<% end_control %>
	<% end_if  %>
	
</div>