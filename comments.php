<?php global $Vuukle, $post; ?>
<div id="comments">

	<div id="respond"></div>

	<div id="vuukle_div"></div>
	<script src="http://vuukle.com/js/vuukle.js" type="text/javascript"></script>
	<script type="text/javascript">create_vuukle_platform('<?php print $Vuukle->Settings['AppId']; ?>', '<?php print $post->ID; ?>', '0', '<?php print strip_tags(get_the_category_list(',', '', $post->ID)); ?>', '<?php the_title_attribute(); ?>', '<?php print $Vuukle->Settings['Param1']; ?>', '<?php print $Vuukle->Settings['Param2']; ?>', '<?php print $Vuukle->Settings['Param3']; ?>');</script>

</div>
