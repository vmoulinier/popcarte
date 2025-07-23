<label class="booked-checkbox {$class}" id="{$id}">
	<button name="{$name}" type="button" class="btn btn-sm btn-default" value=""><span style="width:10px;"
			class="bi"></span>
	</button>
	{$label}
</label>

<script type="text/javascript">
	$(function() {
		$('#{$id}').on('click', function (e)
		{
			var btn = $(this).find('button');
			if (btn.val() == 'true') {
				btn.find('span').removeClass('bi-check-lg');
				btn.val('');
			} else {
				btn.find('span').addClass('bi-check-lg');
				btn.val('true');
			}
			e.preventDefault();
			e.stopPropagation();
		});
	});
</script>