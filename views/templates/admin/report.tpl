{*
* The MIT License (MIT)
*
* Copyright (c) 2015 Benichou
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*  @author    Benichou <benichou.software@gmail.com>
*  @copyright 2015 Benichou
*  @license   http://opensource.org/licenses/MIT  The MIT License (MIT)
*}

{if !empty($tinify_report)}
	<script>
		$(document).ready(function() {
			$('#configuration_form_1').on('submit', function(e) {
				e.preventDefault();

				$.ajax({
					headers: { "cache-control": "no-cache" },
					async: true,
					cache: false,
					type: 'GET',
					url: '{$tinify_cron_url}',
					dataType: 'html',
					success: function() { window.location.reload(); },
					error: function() { window.location.reload(); }
				});
			});
		});
	</script>

	<style>
		.icon-fixed-width {
			display: inline-block;
			width: 1.4em;
		}
	</style>

	<i class="icon-fixed-width icon-rocket"></i><span>{l s='Status: ' mod='tinify'}{$tinify_report->status|escape:'html':'UTF-8'}</span></br>
	<i class="icon-fixed-width icon-calendar"></i><span>{l s='Date: ' mod='tinify'}{$tinify_report->lastUpdate|date_format:"%c"}</span></br>
	<i class="icon-fixed-width icon-file-image-o"></i><span>{l s='Files: ' mod='tinify'}{$tinify_report->fileCounter|intval}</span></br>
	<i class="icon-fixed-width icon-compress"></i><span>{l s='Gain: ' mod='tinify'}{($tinify_report->gainSize / 1024)|intval} ko</span></br>
	<i class="icon-fixed-width icon-money"></i><span>{l s='Compression count: ' mod='tinify'}{$tinify_report->compressionCount|intval}</span></br>
	{foreach from=$tinify_report->error item=error}
		</br><i class="icon-fixed-width icon-times-circle"></i><span>{l s='Error: ' mod='tinify'}{$error|escape:'html':'UTF-8'}</span>
	{/foreach}
	{foreach from=$tinify_report->warning item=warning}
		</br><i class="icon-fixed-width icon-exclamation-triangle"></i><span>{l s='Warning: ' mod='tinify'}{$warning|escape:'html':'UTF-8'}</span>
	{/foreach}
{/if}
