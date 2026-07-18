/**
 * FOTOhub AI — Admin JavaScript
 *
 * Handles modal interactions, AJAX calls, and UI updates.
 *
 * @package FotohubAI
 */

(function ($) {
	'use strict';

	var FotohubAdmin = {

		/**
		 * Initialize all handlers.
		 */
		init: function () {
			this.bindModalEvents();
			this.bindSettingsEvents();
			this.bindMediaEvents();
			this.bindBulkEvents();
			this.bindProductEvents();
			this.bindVideoEvents();
			this.bindStabilityEvents();
			this.bindCopywriterEvents();
			this.bindAnalyticsEvents();
			this.bindSchedulerEvents();
		},

		// =============================================
		// Modal
		// =============================================

		bindModalEvents: function () {
			var self = this;

			// Open modal
			$(document).on('click', '.fotohub-open-generate-modal, #fotohub-generate-btn', function (e) {
				e.preventDefault();
				self.openModal();
			});

			// Close modal
			$(document).on('click', '.fotohub-modal-close, .fotohub-modal-backdrop', function () {
				self.closeModal();
			});

			// ESC key
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' && $('#fotohub-generate-modal').is(':visible')) {
					self.closeModal();
				}
			});

			// Generate button
			$(document).on('click', '#fotohub-generate-submit', function () {
				self.generateImage();
			});

			// Aspect ratio change
			$(document).on('change', '#fotohub-aspect', function () {
				var selected = $(this).find(':selected');
				if (selected.val() === 'custom') {
					$('.fotohub-custom-dims').show();
				} else {
					$('.fotohub-custom-dims').hide();
					$('#fotohub-width').val(selected.data('width'));
					$('#fotohub-height').val(selected.data('height'));
				}
			});
		},

		openModal: function () {
			$('#fotohub-generate-modal').fadeIn(200);
			$('#fotohub-prompt').focus();
			$('body').css('overflow', 'hidden');
		},

		closeModal: function () {
			$('#fotohub-generate-modal').fadeOut(200);
			$('body').css('overflow', '');
		},

		// =============================================
		// Image Generation
		// =============================================

		generateImage: function () {
			var self = this;
			var prompt = $('#fotohub-prompt').val().trim();

			if (!prompt) {
				self.setModalStatus('error', fotohubAI.i18n.error + ' Prompt is required.');
				return;
			}

			var aspect = $('#fotohub-aspect').find(':selected');
			var width = aspect.val() === 'custom' ? $('#fotohub-width').val() : aspect.data('width');
			var height = aspect.val() === 'custom' ? $('#fotohub-height').val() : aspect.data('height');

			var data = {
				action: 'fotohub_generate_image',
				nonce: fotohubAI.nonce,
				prompt: prompt,
				model: $('#fotohub-model').val(),
				num_images: $('#fotohub-num-images').val(),
				width: width,
				height: height
			};

			self.showProgress();
			$('#fotohub-generate-submit').prop('disabled', true).text(fotohubAI.i18n.generating);

			$.post(fotohubAI.ajaxUrl, data, function (response) {
				self.hideProgress();
				$('#fotohub-generate-submit').prop('disabled', false).html(
					'<span class="dashicons dashicons-art" style="vertical-align:middle;margin-right:4px;"></span> Generate'
				);

				if (response.success) {
					self.setModalStatus('success', response.data.message);
					self.showResults(response.data.images);
				} else {
					self.setModalStatus('error', fotohubAI.i18n.error + ' ' + response.data.message);
				}
			}).fail(function () {
				self.hideProgress();
				$('#fotohub-generate-submit').prop('disabled', false);
				self.setModalStatus('error', fotohubAI.i18n.error + ' Network error.');
			});
		},

		showProgress: function () {
			$('#fotohub-generate-progress').show();
			$('.fotohub-progress-fill').addClass('indeterminate');
			$('#fotohub-generate-results').hide();
		},

		hideProgress: function () {
			$('#fotohub-generate-progress').hide();
			$('.fotohub-progress-fill').removeClass('indeterminate');
		},

		showResults: function (images) {
			var $grid = $('#fotohub-generate-results .fotohub-results-grid');
			$grid.empty();

			$.each(images, function (i, img) {
				var html = '<div class="fotohub-result-item">';
				html += '<img src="' + img.thumbnail + '" alt="">';
				html += '<div class="fotohub-result-item-actions">';
				html += '<a href="' + img.url + '" target="_blank">View</a>';
				html += ' | <a href="' + window.location.origin + '/wp-admin/post.php?post=' + img.id + '&action=edit">Edit</a>';
				html += '</div></div>';
				$grid.append(html);
			});

			$('#fotohub-generate-results').show();
		},

		setModalStatus: function (type, message) {
			$('#fotohub-modal-status')
				.removeClass('success error')
				.addClass(type)
				.text(message);
		},

		// =============================================
		// Settings Page
		// =============================================

		bindSettingsEvents: function () {
			var self = this;

			$('#fotohub-test-connection').on('click', function () {
				self.testConnection();
			});
		},

		testConnection: function () {
			var $btn = $('#fotohub-test-connection');
			var $status = $('#fotohub-connection-status');
			var apiKey = $('#fotohub_ai_api_key').val();

			$btn.prop('disabled', true);
			$status.removeClass('success error').html('<span class="fotohub-spinner"></span> Testing...');

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_test_connection',
				nonce: fotohubAI.nonce,
				api_key: apiKey
			}, function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					$status.removeClass('error').addClass('success').text(response.data.message);
				} else {
					$status.removeClass('success').addClass('error').text(response.data.message);
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				$status.removeClass('success').addClass('error').text('Network error');
			});
		},

		// =============================================
		// Media Library Actions
		// =============================================

		bindMediaEvents: function () {
			var self = this;

			// Remove background
			$(document).on('click', '.fotohub-remove-bg', function (e) {
				e.preventDefault();
				var id = $(this).data('id');
				self.processMedia(id, 'fotohub_remove_bg', $(this));
			});

			// Upscale
			$(document).on('click', '.fotohub-upscale', function (e) {
				e.preventDefault();
				var id = $(this).data('id');
				self.processMedia(id, 'fotohub_upscale', $(this));
			});
		},

		processMedia: function (attachmentId, action, $trigger) {
			var originalText = $trigger.text();
			$trigger.text(fotohubAI.i18n.processing);

			$.post(fotohubAI.ajaxUrl, {
				action: action,
				nonce: fotohubAI.nonce,
				attachment_id: attachmentId,
				scale: 2
			}, function (response) {
				if (response.success) {
					$trigger.text(fotohubAI.i18n.complete);
					// Optionally refresh the page to show new attachment
					setTimeout(function () {
						window.location.reload();
					}, 1000);
				} else {
					$trigger.text(originalText);
					alert(fotohubAI.i18n.error + ' ' + response.data.message);
				}
			}).fail(function () {
				$trigger.text(originalText);
				alert(fotohubAI.i18n.error + ' Network error.');
			});
		},

		// =============================================
		// Bulk Generation
		// =============================================

		bindBulkEvents: function () {
			var self = this;

			// Tab switching
			$(document).on('click', '.fotohub-tab', function () {
				var tab = $(this).data('tab');
				$('.fotohub-tab').removeClass('active');
				$(this).addClass('active');
				$('.fotohub-tab-content').removeClass('active');
				$('.fotohub-tab-content[data-tab="' + tab + '"]').addClass('active');
			});

			// Prompt counter
			$('#fotohub-bulk-prompts').on('input', function () {
				var lines = $(this).val().split('\n').filter(function (l) { return l.trim().length > 0; });
				$('#fotohub-prompt-count').text(lines.length);
			});

			// CSV upload
			$('#fotohub-bulk-csv').on('change', function (e) {
				self.handleCSV(e.target.files[0]);
			});

			// Start bulk generation
			$('#fotohub-bulk-start').on('click', function () {
				self.startBulkGeneration();
			});
		},

		handleCSV: function (file) {
			if (!file) return;

			var reader = new FileReader();
			reader.onload = function (e) {
				var lines = e.target.result.split('\n')
					.map(function (l) { return l.trim(); })
					.filter(function (l) { return l.length > 0; });

				// Show preview
				var $preview = $('#fotohub-csv-preview');
				var $list = $preview.find('ul').empty();

				lines.slice(0, 10).forEach(function (line) {
					// Take first column (handles comma-separated CSVs)
					var prompt = line.split(',')[0].replace(/"/g, '').trim();
					$list.append('<li>' + $('<span>').text(prompt).html() + '</li>');
				});

				if (lines.length > 10) {
					$list.append('<li><em>... and ' + (lines.length - 10) + ' more</em></li>');
				}

				$preview.show();

				// Store prompts for generation
				$('#fotohub-bulk-csv').data('prompts', lines.map(function (l) {
					return l.split(',')[0].replace(/"/g, '').trim();
				}));

				$('#fotohub-prompt-count').text(lines.length);
			};
			reader.readAsText(file);
		},

		startBulkGeneration: function () {
			var self = this;
			var prompts = [];

			// Get prompts from active tab
			if ($('.fotohub-tab-content[data-tab="csv"]').hasClass('active')) {
				prompts = $('#fotohub-bulk-csv').data('prompts') || [];
			} else {
				prompts = $('#fotohub-bulk-prompts').val().split('\n').filter(function (l) {
					return l.trim().length > 0;
				});
			}

			if (prompts.length === 0) {
				alert('Please enter at least one prompt.');
				return;
			}

			if (!confirm(fotohubAI.i18n.confirmBulk)) {
				return;
			}

			var dims = $('#fotohub-bulk-dimensions').val().split('x');

			$('.fotohub-bulk-output').show();
			$('#fotohub-bulk-start').prop('disabled', true);

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_bulk_generate',
				nonce: fotohubAI.nonce,
				prompts: prompts,
				model: $('#fotohub-bulk-model').val(),
				width: dims[0],
				height: dims[1]
			}, function (response) {
				$('#fotohub-bulk-start').prop('disabled', false);
				$('#fotohub-bulk-progress-fill').css('width', '100%');

				if (response.success) {
					$('#fotohub-bulk-progress-text').text(response.data.message);
					self.showBulkResults(response.data.results, response.data.errors);
				} else {
					$('#fotohub-bulk-progress-text').text(fotohubAI.i18n.error + ' ' + response.data.message);
				}
			}).fail(function () {
				$('#fotohub-bulk-start').prop('disabled', false);
				$('#fotohub-bulk-progress-text').text(fotohubAI.i18n.error + ' Network error.');
			});

			// Animate progress
			$('#fotohub-bulk-progress-fill').css('width', '0%');
			setTimeout(function () {
				$('#fotohub-bulk-progress-fill').css('width', '60%');
			}, 500);
			$('#fotohub-bulk-progress-text').text(fotohubAI.i18n.generating);
		},

		showBulkResults: function (results, errors) {
			var $grid = $('#fotohub-bulk-results .fotohub-results-grid').empty();

			$.each(results, function (i, item) {
				var html = '<div class="fotohub-result-item">';
				html += '<img src="' + item.thumbnail + '" alt="" title="' + $('<span>').text(item.prompt).html() + '">';
				html += '<div class="fotohub-result-item-actions">';
				html += '<a href="' + item.url + '" target="_blank">View</a>';
				html += ' | <a href="' + item.url + '" download>Download</a>';
				html += '</div></div>';
				$grid.append(html);
			});

			if (errors && errors.length > 0) {
				var $errors = $('#fotohub-bulk-errors');
				var $list = $errors.find('ul').empty();
				$.each(errors, function (i, err) {
					$list.append('<li>' + $('<span>').text(err.prompt + ': ' + err.message).html() + '</li>');
				});
				$errors.show();
			}
		},

		// =============================================
		// WooCommerce Product
		// =============================================

		bindProductEvents: function () {
			var self = this;

			$(document).on('click', '.fotohub-generate-product-photos', function () {
				self.generateProductPhotos($(this));
			});
		},

		generateProductPhotos: function ($btn) {
			var productId = $btn.data('product-id');
			var $status = $btn.siblings('.fotohub-product-status');

			$btn.prop('disabled', true);
			$status.html('<span class="fotohub-spinner"></span> ' + fotohubAI.i18n.generating);

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_generate_product_photos',
				nonce: fotohubAI.nonce,
				product_id: productId,
				prompt: $('#fotohub_product_prompt').val(),
				num_images: $('#fotohub_product_num_images').val(),
				style: $('#fotohub_product_style').val()
			}, function (response) {
				$btn.prop('disabled', false);

				if (response.success) {
					$status.text(response.data.message).css('color', '#00a32a');

					// Show generated images
					var $gallery = $btn.closest('.fotohub-product-panel').find('.fotohub-product-gallery');
					$gallery.empty();
					$.each(response.data.images, function (i, img) {
						$gallery.append('<img src="' + img.thumbnail + '" alt="">');
					});
					$btn.closest('.fotohub-product-panel').find('.fotohub-product-results').show();
				} else {
					$status.text(fotohubAI.i18n.error + ' ' + response.data.message).css('color', '#d63638');
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				$status.text(fotohubAI.i18n.error + ' Network error.').css('color', '#d63638');
			});
		},

		// =============================================
		// Video Generation
		// =============================================

		bindVideoEvents: function () {
			var self = this;

			$(document).on('click', '.fotohub-open-video-modal, #fotohub-video-btn', function (e) {
				e.preventDefault();
				self.openVideoModal();
			});

			$(document).on('click', '#fotohub-video-modal .fotohub-modal-close, #fotohub-video-modal .fotohub-modal-backdrop', function () {
				self.closeVideoModal();
			});

			$(document).on('click', '#fotohub-video-submit', function () {
				self.generateVideo();
			});

			// Reference image selection
			$(document).on('click', '#fotohub-video-select-image', function (e) {
				e.preventDefault();
				var frame = wp.media({
					title: 'Select Reference Image',
					button: { text: 'Use Image' },
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#fotohub-video-reference-url').val(attachment.url);
					$('#fotohub-video-reference-preview').attr('src', attachment.url).show();
				});
				frame.open();
			});
		},

		openVideoModal: function () {
			$('#fotohub-video-modal').fadeIn(200);
			$('body').css('overflow', 'hidden');
		},

		closeVideoModal: function () {
			$('#fotohub-video-modal').fadeOut(200);
			$('body').css('overflow', '');
		},

		generateVideo: function () {
			var self = this;
			var prompt = $('#fotohub-video-prompt').val().trim();

			if (!prompt) {
				self.setVideoStatus('error', 'Prompt is required.');
				return;
			}

			var data = {
				action: 'fotohub_generate_video',
				nonce: fotohubAI.nonce,
				prompt: prompt,
				model: $('#fotohub-video-model').val(),
				duration: $('#fotohub-video-duration').val(),
				aspect_ratio: $('#fotohub-video-aspect').val(),
				image_url: $('#fotohub-video-reference-url').val(),
				post_id: $('#fotohub-video-post-id').val() || 0
			};

			$('#fotohub-video-submit').prop('disabled', true).text('Generating...');
			$('#fotohub-video-progress').show();
			self.setVideoStatus('', 'Video generation started. This may take 1-5 minutes...');

			$.post(fotohubAI.ajaxUrl, data, function (response) {
				if (response.success) {
					self.setVideoStatus('success', response.data.message);
					if (response.data.job_id) {
						self.pollVideoStatus(response.data.job_id);
					}
				} else {
					$('#fotohub-video-submit').prop('disabled', false).text('Generate Video');
					$('#fotohub-video-progress').hide();
					self.setVideoStatus('error', response.data.message);
				}
			}).fail(function () {
				$('#fotohub-video-submit').prop('disabled', false).text('Generate Video');
				$('#fotohub-video-progress').hide();
				self.setVideoStatus('error', 'Network error.');
			});
		},

		pollVideoStatus: function (jobId) {
			var self = this;
			var attempts = 0;
			var maxAttempts = 60; // 5 minutes at 5s intervals

			var poller = setInterval(function () {
				attempts++;
				if (attempts > maxAttempts) {
					clearInterval(poller);
					$('#fotohub-video-submit').prop('disabled', false).text('Generate Video');
					self.setVideoStatus('error', 'Video generation timed out. Check your dashboard.');
					return;
				}

				$.post(fotohubAI.ajaxUrl, {
					action: 'fotohub_check_video_status',
					nonce: fotohubAI.nonce,
					job_id: jobId
				}, function (response) {
					if (response.success) {
						if (response.data.status === 'completed') {
							clearInterval(poller);
							$('#fotohub-video-submit').prop('disabled', false).text('Generate Video');
							$('#fotohub-video-progress').hide();
							self.setVideoStatus('success', 'Video ready!');
							if (response.data.url) {
								self.showVideoResult(response.data.url);
							}
						} else if (response.data.status === 'failed') {
							clearInterval(poller);
							$('#fotohub-video-submit').prop('disabled', false).text('Generate Video');
							$('#fotohub-video-progress').hide();
							self.setVideoStatus('error', 'Video generation failed.');
						}
					}
				});
			}, 5000);
		},

		showVideoResult: function (url) {
			var html = '<div class="fotohub-video-result">';
			html += '<video controls width="100%" src="' + url + '"></video>';
			html += '<div class="fotohub-result-item-actions">';
			html += '<a href="' + url + '" target="_blank">Open</a>';
			html += ' | <a href="' + url + '" download>Download</a>';
			html += '</div></div>';
			$('#fotohub-video-results').html(html).show();
		},

		setVideoStatus: function (type, message) {
			$('#fotohub-video-status').removeClass('success error').addClass(type).text(message);
		},

		// =============================================
		// Stability Tools
		// =============================================

		bindStabilityEvents: function () {
			var self = this;

			// Tool selection
			$(document).on('click', '.fotohub-stability-tool-card', function () {
				var toolId = $(this).data('tool-id');
				self.selectStabilityTool(toolId);
			});

			// Image selection for stability tools
			$(document).on('click', '#fotohub-stability-select-image', function (e) {
				e.preventDefault();
				var frame = wp.media({
					title: 'Select Image',
					button: { text: 'Use Image' },
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#fotohub-stability-attachment-id').val(attachment.id);
					$('#fotohub-stability-preview').attr('src', attachment.url).show();
					self.initMaskCanvas(attachment.url);
				});
				frame.open();
			});

			// Reference image for style transfer
			$(document).on('click', '#fotohub-stability-select-reference', function (e) {
				e.preventDefault();
				var frame = wp.media({
					title: 'Select Reference Image',
					button: { text: 'Use Image' },
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#fotohub-stability-reference-id').val(attachment.id);
					$('#fotohub-stability-reference-preview').attr('src', attachment.url).show();
				});
				frame.open();
			});

			// Run tool
			$(document).on('click', '#fotohub-stability-run', function () {
				self.runStabilityTool();
			});

			// Mask drawing controls
			$(document).on('click', '#fotohub-mask-clear', function () {
				self.clearMask();
			});

			$(document).on('input', '#fotohub-mask-brush-size', function () {
				self.maskBrushSize = parseInt($(this).val());
			});

			// Row action handlers in media library
			$(document).on('click', '.fotohub-stability-action', function (e) {
				e.preventDefault();
				var toolId = $(this).data('tool');
				var attachId = $(this).data('id');
				self.quickStabilityTool(toolId, attachId, $(this));
			});
		},

		selectStabilityTool: function (toolId) {
			var self = this;
			$('.fotohub-stability-tool-card').removeClass('active');
			$('.fotohub-stability-tool-card[data-tool-id="' + toolId + '"]').addClass('active');
			$('#fotohub-stability-active-tool').val(toolId);

			// Show/hide relevant fields
			var needsMask = ['erase', 'inpaint'].indexOf(toolId) !== -1;
			var needsPrompt = ['creative-upscale', 'inpaint', 'search-and-replace', 'control-sketch', 'control-structure'].indexOf(toolId) !== -1;
			var needsReference = ['style-transfer'].indexOf(toolId) !== -1;
			var needsSearch = ['search-and-replace', 'search-and-recolor'].indexOf(toolId) !== -1;
			var needsPadding = ['outpaint'].indexOf(toolId) !== -1;
			var needsColor = ['search-and-recolor'].indexOf(toolId) !== -1;

			$('#fotohub-stability-mask-section').toggle(needsMask);
			$('#fotohub-stability-prompt-section').toggle(needsPrompt);
			$('#fotohub-stability-reference-section').toggle(needsReference);
			$('#fotohub-stability-search-section').toggle(needsSearch);
			$('#fotohub-stability-padding-section').toggle(needsPadding);
			$('#fotohub-stability-color-section').toggle(needsColor);

			$('#fotohub-stability-tool-panel').show();
		},

		maskCanvas: null,
		maskCtx: null,
		maskDrawing: false,
		maskBrushSize: 20,

		initMaskCanvas: function (imageUrl) {
			var self = this;
			var canvas = document.getElementById('fotohub-mask-canvas');
			if (!canvas) return;

			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () {
				canvas.width = img.naturalWidth;
				canvas.height = img.naturalHeight;
				// Scale canvas display
				var maxWidth = 500;
				var scale = Math.min(maxWidth / img.naturalWidth, 1);
				canvas.style.width = (img.naturalWidth * scale) + 'px';
				canvas.style.height = (img.naturalHeight * scale) + 'px';

				self.maskCanvas = canvas;
				self.maskCtx = canvas.getContext('2d');
				self.maskCtx.fillStyle = '#000000';
				self.maskCtx.fillRect(0, 0, canvas.width, canvas.height);

				// Mouse events for drawing
				$(canvas).off().on('mousedown', function (e) {
					self.maskDrawing = true;
					self.drawMask(e);
				}).on('mousemove', function (e) {
					if (self.maskDrawing) self.drawMask(e);
				}).on('mouseup mouseleave', function () {
					self.maskDrawing = false;
				});
			};
			img.src = imageUrl;
		},

		drawMask: function (e) {
			var canvas = this.maskCanvas;
			var rect = canvas.getBoundingClientRect();
			var scaleX = canvas.width / rect.width;
			var scaleY = canvas.height / rect.height;
			var x = (e.clientX - rect.left) * scaleX;
			var y = (e.clientY - rect.top) * scaleY;

			this.maskCtx.fillStyle = '#ffffff';
			this.maskCtx.beginPath();
			this.maskCtx.arc(x, y, this.maskBrushSize * scaleX, 0, Math.PI * 2);
			this.maskCtx.fill();
		},

		clearMask: function () {
			if (this.maskCtx && this.maskCanvas) {
				this.maskCtx.fillStyle = '#000000';
				this.maskCtx.fillRect(0, 0, this.maskCanvas.width, this.maskCanvas.height);
			}
		},

		getMaskBase64: function () {
			if (!this.maskCanvas) return '';
			return this.maskCanvas.toDataURL('image/png').split(',')[1];
		},

		runStabilityTool: function () {
			var self = this;
			var toolId = $('#fotohub-stability-active-tool').val();
			var attachmentId = $('#fotohub-stability-attachment-id').val();

			if (!toolId || !attachmentId) {
				alert('Please select a tool and an image.');
				return;
			}

			var data = {
				action: 'fotohub_stability_tool',
				nonce: fotohubAI.nonce,
				tool_id: toolId,
				attachment_id: attachmentId,
				output_format: $('#fotohub-stability-format').val() || 'png'
			};

			// Add tool-specific data
			if ($('#fotohub-stability-prompt').val()) {
				data.prompt = $('#fotohub-stability-prompt').val();
			}
			if (['erase', 'inpaint'].indexOf(toolId) !== -1) {
				data.mask = self.getMaskBase64();
			}
			if ($('#fotohub-stability-reference-id').val()) {
				data.reference_id = $('#fotohub-stability-reference-id').val();
			}
			if ($('#fotohub-stability-search').val()) {
				data.search_prompt = $('#fotohub-stability-search').val();
			}
			if ($('#fotohub-stability-replace').val()) {
				data.replace_prompt = $('#fotohub-stability-replace').val();
			}
			if ($('#fotohub-stability-color').val()) {
				data.color = $('#fotohub-stability-color').val();
			}
			if (toolId === 'outpaint') {
				data.padding = {
					left: parseInt($('#fotohub-outpaint-left').val()) || 0,
					right: parseInt($('#fotohub-outpaint-right').val()) || 0,
					top: parseInt($('#fotohub-outpaint-top').val()) || 0,
					bottom: parseInt($('#fotohub-outpaint-bottom').val()) || 0
				};
			}

			$('#fotohub-stability-run').prop('disabled', true).text('Processing...');
			$('#fotohub-stability-progress').show();

			$.post(fotohubAI.ajaxUrl, data, function (response) {
				$('#fotohub-stability-run').prop('disabled', false).text('Run Tool');
				$('#fotohub-stability-progress').hide();

				if (response.success) {
					self.showStabilityResult(response.data);
				} else {
					alert(fotohubAI.i18n.error + ' ' + response.data.message);
				}
			}).fail(function () {
				$('#fotohub-stability-run').prop('disabled', false).text('Run Tool');
				$('#fotohub-stability-progress').hide();
				alert(fotohubAI.i18n.error + ' Network error.');
			});
		},

		showStabilityResult: function (data) {
			var html = '<div class="fotohub-stability-result">';
			html += '<h4>Result</h4>';
			html += '<img src="' + data.url + '" alt="" style="max-width:100%;">';
			html += '<p>' + data.message + '</p>';
			html += '<div class="fotohub-result-item-actions">';
			html += '<a href="' + data.url + '" target="_blank">View Full</a>';
			html += ' | <a href="' + window.location.origin + '/wp-admin/post.php?post=' + data.id + '&action=edit">Edit in Library</a>';
			html += '</div></div>';
			$('#fotohub-stability-results').html(html).show();
		},

		quickStabilityTool: function (toolId, attachmentId, $trigger) {
			var originalText = $trigger.text();
			$trigger.text(fotohubAI.i18n.processing);

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_stability_tool',
				nonce: fotohubAI.nonce,
				tool_id: toolId,
				attachment_id: attachmentId,
				output_format: 'png'
			}, function (response) {
				if (response.success) {
					$trigger.text(fotohubAI.i18n.complete);
					setTimeout(function () { window.location.reload(); }, 1000);
				} else {
					$trigger.text(originalText);
					alert(fotohubAI.i18n.error + ' ' + response.data.message);
				}
			}).fail(function () {
				$trigger.text(originalText);
				alert(fotohubAI.i18n.error + ' Network error.');
			});
		},

		// =============================================
		// AI Copywriter
		// =============================================

		bindCopywriterEvents: function () {
			var self = this;

			// Generate titles
			$(document).on('click', '#fotohub-ai-titles', function () {
				var content = $('#title').val() || $('#post_title').val() || '';
				if (!content) {
					alert('Please enter a topic or existing title first.');
					return;
				}
				self.generateCopy('title', 'Generate compelling title options for: ' + content);
			});

			// Generate excerpt
			$(document).on('click', '#fotohub-ai-excerpt', function () {
				var content = self.getPostContent();
				if (!content) {
					alert('Please write some content first.');
					return;
				}
				self.generateCopy('excerpt', content);
			});

			// Generate article from outline
			$(document).on('click', '#fotohub-ai-article', function () {
				var outline = $('#fotohub-ai-outline').val();
				if (!outline) {
					alert('Please enter an outline first.');
					return;
				}
				self.generateCopy('article', outline);
			});

			// Generate product description
			$(document).on('click', '#fotohub-ai-product-desc', function () {
				var name = $('#title').val() || '';
				var details = $('#content').val() || $('#excerpt').val() || '';
				var tone = $('#fotohub-ai-tone').val() || 'professional';
				self.generateCopy('product_description', 'Product: ' + name + '\nDetails: ' + details, tone);
			});

			// Generate slug
			$(document).on('click', '#fotohub-ai-slug', function () {
				var title = $('#title').val() || $('#post_title').val() || '';
				if (!title) {
					alert('Please enter a title first.');
					return;
				}
				self.generateCopy('slug', title);
			});

			// Generate alt text for single image
			$(document).on('click', '.fotohub-ai-alt-text', function () {
				var attachId = $(this).data('id');
				self.generateAltText(attachId, $(this));
			});

			// Bulk alt text
			$(document).on('click', '#fotohub-bulk-alt-text-btn', function () {
				self.bulkAltText($(this));
			});
		},

		generateCopy: function (type, content, tone) {
			var self = this;
			var $output = $('#fotohub-ai-output');
			$output.html('<span class="fotohub-spinner"></span> Generating...').show();

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_generate_copy',
				nonce: fotohubAI.nonce,
				type: type,
				content: content,
				tone: tone || 'professional'
			}, function (response) {
				if (response.success) {
					var html = '<div class="fotohub-ai-result">';
					html += '<h4>' + response.data.message + '</h4>';
					html += '<div class="fotohub-ai-content">' + self.formatCopyOutput(response.data.content, type) + '</div>';
					html += '<button type="button" class="button fotohub-ai-apply" data-type="' + type + '">Apply</button>';
					html += '</div>';
					$output.html(html);

					// Store for apply action
					$output.data('generated-content', response.data.content);
				} else {
					$output.html('<p class="error">' + response.data.message + '</p>');
				}
			}).fail(function () {
				$output.html('<p class="error">Network error.</p>');
			});
		},

		formatCopyOutput: function (content, type) {
			if (type === 'title') {
				// Try to parse as JSON array
				try {
					var titles = JSON.parse(content);
					if (Array.isArray(titles)) {
						var html = '<ul class="fotohub-title-options">';
						titles.forEach(function (t, i) {
							html += '<li><label><input type="radio" name="fotohub_title" value="' + i + '"> ' + $('<span>').text(t).html() + '</label></li>';
						});
						html += '</ul>';
						return html;
					}
				} catch (e) {}
			}
			return '<pre>' + $('<span>').text(content).html() + '</pre>';
		},

		getPostContent: function () {
			// Try Gutenberg
			if (window.wp && wp.data && wp.data.select('core/editor')) {
				return wp.data.select('core/editor').getEditedPostContent();
			}
			// Classic editor
			if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
				return tinymce.get('content').getContent({ format: 'text' });
			}
			return $('#content').val() || '';
		},

		generateAltText: function (attachmentId, $trigger) {
			var originalText = $trigger.text();
			$trigger.text('Analyzing...');

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_analyze_image',
				nonce: fotohubAI.nonce,
				attachment_id: attachmentId
			}, function (response) {
				if (response.success) {
					$trigger.text('Done!').css('color', '#00a32a');
					// Update alt text field if visible
					$('input[name="attachments[' + attachmentId + '][alt]"]').val(response.data.alt_text);
				} else {
					$trigger.text(originalText);
					alert(response.data.message);
				}
			}).fail(function () {
				$trigger.text(originalText);
			});
		},

		bulkAltText: function ($btn) {
			$btn.prop('disabled', true).text('Processing...');
			var $status = $('#fotohub-bulk-alt-status');

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_bulk_alt_text',
				nonce: fotohubAI.nonce,
				batch_size: 10
			}, function (response) {
				$btn.prop('disabled', false).text('Bulk Generate Alt Text');
				if (response.success) {
					$status.text(response.data.message).css('color', '#00a32a');
				} else {
					$status.text(response.data.message).css('color', '#d63638');
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Bulk Generate Alt Text');
				$status.text('Network error.').css('color', '#d63638');
			});
		},

		// =============================================
		// Analytics
		// =============================================

		bindAnalyticsEvents: function () {
			var self = this;

			// Period selector
			$(document).on('change', '#fotohub-analytics-period', function () {
				self.loadAnalytics($(this).val());
			});

			// Export CSV
			$(document).on('click', '#fotohub-export-csv', function () {
				self.exportCSV();
			});

			// Auto-load on analytics page
			if ($('.fotohub-analytics-page').length) {
				self.loadAnalytics('month');
			}
		},

		loadAnalytics: function (period) {
			var self = this;
			$('#fotohub-analytics-loading').show();

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_get_analytics',
				nonce: fotohubAI.nonce,
				period: period
			}, function (response) {
				$('#fotohub-analytics-loading').hide();
				if (response.success) {
					self.renderAnalytics(response.data);
				}
			}).fail(function () {
				$('#fotohub-analytics-loading').hide();
			});
		},

		renderAnalytics: function (data) {
			var stats = data.stats || {};
			var balance = data.balance || {};

			// Update stat cards
			$('#fotohub-stat-total').text(stats.total_generations || 0);
			$('#fotohub-stat-credits-used').text(stats.credits_used || 0);
			$('#fotohub-stat-credits-remaining').text(balance.credits || balance.balance || 'N/A');
			$('#fotohub-stat-avg-cost').text(stats.avg_cost ? stats.avg_cost.toFixed(2) : '0.00');

			// Render charts if Chart.js is available
			if (window.Chart && data.models) {
				this.renderModelChart(data.models);
			}
		},

		renderModelChart: function (models) {
			var canvas = document.getElementById('fotohub-models-chart');
			if (!canvas) return;

			// Destroy existing chart
			if (this.modelsChart) {
				this.modelsChart.destroy();
			}

			var labels = Object.keys(models);
			var values = Object.values(models);

			this.modelsChart = new Chart(canvas, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{
						label: 'Generations',
						data: values,
						backgroundColor: '#4f46e5',
						borderRadius: 4
					}]
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					plugins: {
						legend: { display: false }
					}
				}
			});
		},

		modelsChart: null,

		exportCSV: function () {
			var $btn = $('#fotohub-export-csv');
			$btn.prop('disabled', true).text('Exporting...');

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_export_csv',
				nonce: fotohubAI.nonce
			}, function (response) {
				$btn.prop('disabled', false).text('Export CSV');
				if (response.success && response.data.url) {
					window.location.href = response.data.url;
				} else {
					alert(response.data.message || 'Export failed.');
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Export CSV');
			});
		},

		// =============================================
		// Scheduler
		// =============================================

		bindSchedulerEvents: function () {
			var self = this;

			// Tab filtering
			$(document).on('click', '.fotohub-scheduler-tab', function () {
				var status = $(this).data('status');
				$('.fotohub-scheduler-tab').removeClass('active');
				$(this).addClass('active');
				self.filterJobs(status);
			});

			// Schedule new job
			$(document).on('click', '#fotohub-schedule-new-job', function () {
				$('#fotohub-schedule-form').toggle();
			});

			$(document).on('click', '#fotohub-schedule-submit', function () {
				self.scheduleJob();
			});

			// Cancel job
			$(document).on('click', '.fotohub-cancel-job', function () {
				var jobId = $(this).data('job-id');
				self.cancelJob(jobId, $(this));
			});

			// Auto refresh toggle
			$(document).on('change', '#fotohub-auto-refresh', function () {
				if ($(this).is(':checked')) {
					self.startAutoRefresh();
				} else {
					self.stopAutoRefresh();
				}
			});
		},

		schedulerInterval: null,

		filterJobs: function (status) {
			if (status === 'all') {
				$('.fotohub-job-row').show();
			} else {
				$('.fotohub-job-row').hide();
				$('.fotohub-job-row[data-status="' + status + '"]').show();
			}
		},

		scheduleJob: function () {
			var jobType = $('#fotohub-job-type').val();
			var payload = $('#fotohub-job-payload').val();
			var scheduledAt = $('#fotohub-job-scheduled-at').val();

			if (!jobType) {
				alert('Please select a job type.');
				return;
			}

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_schedule_job',
				nonce: fotohubAI.nonce,
				job_type: jobType,
				payload: payload,
				scheduled_at: scheduledAt
			}, function (response) {
				if (response.success) {
					alert(response.data.message);
					window.location.reload();
				} else {
					alert(response.data.message);
				}
			});
		},

		cancelJob: function (jobId, $trigger) {
			if (!confirm('Cancel this job?')) return;
			$trigger.text('Cancelling...');

			$.post(fotohubAI.ajaxUrl, {
				action: 'fotohub_schedule_job',
				nonce: fotohubAI.nonce,
				job_type: 'cancel',
				payload: JSON.stringify({ job_id: jobId })
			}, function (response) {
				if (response.success) {
					window.location.reload();
				} else {
					$trigger.text('Cancel');
					alert(response.data.message);
				}
			});
		},

		startAutoRefresh: function () {
			var self = this;
			this.schedulerInterval = setInterval(function () {
				if ($('.fotohub-job-row[data-status="running"]').length > 0) {
					window.location.reload();
				}
			}, 30000);
		},

		stopAutoRefresh: function () {
			if (this.schedulerInterval) {
				clearInterval(this.schedulerInterval);
				this.schedulerInterval = null;
			}
		}
	};

	// Initialize on DOM ready
	$(document).ready(function () {
		FotohubAdmin.init();
	});

})(jQuery);
