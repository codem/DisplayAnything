<div>
	
	<h2>$Title</h2>

	$Content
	
	<% if SingleFile %>
		<% control SingleFile %>
			<div id="ImageGallery">
				<h4><a href="$Link">$Name</a></h4>
				<p>$Caption</p>
				<div class="desc">
					$Description
				</div>
			</div>
		<% end_control %>
	<% end_if  %>
	
</div>