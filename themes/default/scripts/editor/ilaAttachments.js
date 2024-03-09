/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * Initialize the inline attachments posting interface
 *
 * Done as Immediately-Invoked Function Expression, IIFE
 */
(function (){
	var ElkInlineAttachments = (function (selector, editor, opt)
	{
		'use strict';

		opt = $.extend({
			inlineSelector: '.inline_insert',
			data: 'attachid',
			addAfter: 'label',
			template: ''
		}, opt);

		var listAttachs = [],
			init = function (opt) {},
			addInterface = function ($before, attachId)
			{
				var $trigger,
					$container = $('<div class="ila_container" />'),
					$over;

				if (typeof opt.trigger !== 'undefined')
				{
					$trigger = opt.trigger.clone();
				}
				else
				{
					$trigger = $('<a />');

					if (typeof opt.triggerClass !== 'undefined')
					{
						$trigger.addClass(opt.triggerClass);
					}
				}

				$container.append($trigger);
				$trigger.on('click', function (e)
				{
					e.preventDefault();

					if (typeof $over !== 'undefined')
					{
						$(document).trigger('click.ila_insert');
						return;
					}

					$over = $(opt.template).hide();
					var firstLi = false,
						$tabs = $over.find("ul[data-group='tabs'] li");
					/*
					 * Behaviours (onSomething)
					 */
					$tabs.each(function (k, v)
					{
						$(this).on('click', function (e)
						{
							e.preventDefault();
							e.stopPropagation();

							$tabs.each(function (k, v)
							{
								$(this).removeClass('active');
							});
							var toShow = $(this).data('tab');
							$(this).addClass('active');
							$over.find('.container').each(function (k, v)
							{
								if ($(this).data('visual') === toShow)
								{
									$(this).show();
								}
								else
								{
									$(this).hide();
								}
							});
						});
						if (firstLi === false)
						{
							$(this).trigger('click');
							firstLi = true;
						}
					});
					$over.find("input[data-size='thumb']").on('change', function (e)
					{
						$over.find('.customsize').slideUp();
					});
					$over.find("input[data-size='full']").on('change', function (e)
					{
						$over.find('.customsize').slideUp();
					});
					$over.find("input[data-size='cust']").on('change', function (e)
					{
						$over.find('.customsize').slideDown();
					});
					$over.find(".range").on('input', function ()
					{
						var val = $(this).val();
						$over.find(".visualizesize").val(val + 'px');
					}).trigger('input');

					$over.find('.button').on('click', function ()
					{
						var ila_text = '[attach';
						if ($over.find("input[data-size='thumb']").is(':checked'))
						{
							ila_text = ila_text + ' type=thumb';
						}
						else if ($over.find("input[data-size='cust']").is(':checked'))
						{
							var w = $over.find('.range').val();
							// Doesn't really matter that much, but just to ensure it's not 1
							if (w > 10)
							{
								ila_text = ila_text + ' width=' + w;
							}
						}
						else if ($over.find("input[data-size='full']").is(':checked'))
						{
							ila_text = ila_text + ' type=image';
						}

						$over.find(".container[data-visual='align'] input").each(function (k, v)
						{
							if ($(this).is(':checked'))
							{
								if ($(this).data('align') !== 'none')
								{
									ila_text = ila_text + ' align=' + $(this).data('align');

								}
							}
						});

						ila_text = ila_text + ']' + attachId + '[/attach]';
						$editor_data[editor].insertText(ila_text, false, true);
						$(document).trigger('click.ila_insert');
					});
					// Prevents removing the element to disappear when clicking on
					// anything because of the click.ila_insert event
					$over.find('*').on('click', function (e)
					{
						e.stopPropagation();
					});

					/*
					 * Initialization
					 */
					$over.find('.ila_container label:first-child input').each(function (k, v)
					{
						$(this).change().prop('checked', true);
					});

					$container.append($over);
					$over.fadeIn(function ()
					{
						$(document).on('click.ila_insert', function ()
						{
							$over.fadeOut(function ()
							{
								$over.remove();
								$over = undefined;
							});
							$(document).off('click.ila_insert');
						});
					});
				}).attr('id', 'inline_attach_' + attachId)
					.data('attachid', attachId);

				$before.after($container);
				listAttachs.push($trigger);
			},
			removeAttach = function (attachId)
			{
				var tmpList = [],
					i;

				for (i = 0; i < listAttachs.length; i++)
				{
					if (listAttachs[i].data('attachid') === attachId)
					{
						break;
					}

					tmpList.push(listAttachs[i]);
				}

				i++;
				for (; i < listAttachs.length; i++)
				{
					tmpList.push(listAttachs[i]);
				}

				listAttachs = tmpList;
				$('#inline_attach_' + attachId).remove();
			};

		init(opt);
		return {
			addInterface: addInterface,
			removeAttach: removeAttach
		};
	});

	// AMD / RequireJS
	if (typeof define !== 'undefined' && define.amd)
	{
		define([], function ()
		{
			return ElkInlineAttachments;
		});
	}
	// CommonJS
	else if (typeof module !== 'undefined' && module.exports)
	{
		module.exports = ElkInlineAttachments;
	}
	// included directly via <script> tag
	else
	{
		this.ElkInlineAttachments = ElkInlineAttachments;
	}
})();
