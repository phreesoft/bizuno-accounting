/*
 * EasyUI extensions combined into a single file
 *
 * NOTICE OF LICENSE
 * This source file is subject to the license of the developer.
 * License for non-commercial use is available at this URL:
 * https://www.jeasyui.com/license_freeware.php
 * License for commercial use is available at this URL:
 * https://www.jeasyui.com/license_commercial.php
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @license    See above
 * @version    6.x Last Update: 2020-09-23
 * @filesource /view/easyUI/jquery-easyui/easyui-extensions.js
 *
 * Contains the following EasyUI Extensions in this single file
 * portal - EasyUI Portal extension
 * color - EasyUI Color extension
 * editable datagrid - jQuery EasyUI Editable DataGrid
 * datagrid-filter - jQuery EasyUI Datagrid Filter
 * datagrid-drag and drop - jQuery EasyUI Datagrid Drag-n-Drop rows
 * datagrid-detailview - MOVED TO common.js TO HANDLE noConflict behavior
 */

/**
 * portal - EasyUI Portal Extension
 */
(function($){
	/**
	 * initialize the portal
	 */
	function init(target){
		$(target).addClass('portal');
		var table = $('<table border="0" cellspacing="0" cellpadding="0"><tr></tr></table>').appendTo(target);
		var tr = table.find('tr');

		var columnWidths = [];
		var totalWidth = 0;
		$(target).children('div:first').addClass('portal-column-left');
		$(target).children('div:last').addClass('portal-column-right');
		$(target).find('>div').each(function(){	// each column panel
			var column = $(this);
			totalWidth += column.outerWidth();
			columnWidths.push(column.outerWidth());

			var td = $('<td class="portal-column-td"></td>').appendTo(tr)
			column.addClass('portal-column').appendTo(td);
			column.find('>div').each(function(){	// each portal panel
				var p = $(this).addClass('portal-p').panel({
					doSize:false,
					cls:'portal-panel'
				});
				makeDraggable(target, p);
			});
		});
		for(var i=0; i<columnWidths.length; i++){
			columnWidths[i] /= totalWidth;
		}

		$(target).bind('_resize', function(){
			var opts = $.data(target, 'portal').options;
			if (opts.fit == true){
				setSize(target);
			}
			return false;
		});

		return columnWidths;
	}

	function initCss(){
		if (!$('#easyui-portal-style').length){
			$('head').append(
				'<style id="easyui-portal-style">' +
				'.portal{padding:0;margin:0;overflow:auto;border:1px solid #99bbe8;}' +
				'.portal-noborder{border:0;}' +
				'.portal .portal-panel{margin-bottom:10px;}' +
				'.portal-column-td{vertical-align:top;}' +
				'.portal-column{padding:10px 0 10px 10px;overflow:hidden;}' +
				'.portal-column-left{padding-left:10px;}' +
				'.portal-column-right{padding-right:10px;}' +
				'.portal-proxy{opacity:0.6;filter:alpha(opacity=60);}' +
				'.portal-spacer{border:3px dashed #eee;margin-bottom:10px;}' +
				'</style>'
			);
		}
	}

	function setSize(target){
		var t = $(target);
		var opts = $.data(target, 'portal').options;
		if (opts.fit){
			var p = t.parent();
			opts.width = p.width();
			opts.height = p.height();
		}
		if (!isNaN(opts.width)){
			t._outerWidth(opts.width);
		} else {
			t.width('auto');
		}
		if (!isNaN(opts.height)){
			t._outerHeight(opts.height);
		} else {
			t.height('auto');
		}

		var hasScroll = t.find('>table').outerHeight() > t.height();
		var width = t.width();
		var columnWidths = $.data(target, 'portal').columnWidths;
		var leftWidth = 0;

		// calculate and set every column size
		for(var i=0; i<columnWidths.length; i++){
			var p = t.find('div.portal-column:eq('+i+')');
			var w = Math.floor(width * columnWidths[i]);
			if (i == columnWidths.length - 1){
//				w = width - leftWidth - (hasScroll == true ? 28 : 10);
				w = width - leftWidth - (hasScroll == true ? 18 : 0);
			}
			p._outerWidth(w);
			leftWidth += p.outerWidth();

			// resize every panel of the column
			p.find('div.portal-p').panel('resize', {width:p.width()});
		}
		opts.onResize.call(target, opts.width, opts.height);
	}

	/**
	 * set draggable feature for the specified panel
	 */
	function makeDraggable(target, panel){
		var spacer;
		panel.panel('panel').draggable({
			handle:'>div.panel-header>div.panel-title',
			proxy:function(source){
				var p = $('<div class="portal-proxy">proxy</div>').insertAfter(source);
				p.width($(source).width());
				p.height($(source).height());
				p.html($(source).html());
				p.find('div.portal-p').removeClass('portal-p');
				return p;
			},
			onBeforeDrag:function(e){
				e.data.startTop = $(this).position().top + $(target).scrollTop();
			},
			onStartDrag:function(e){
				$(this).hide();
				spacer = $('<div class="portal-spacer"></div>').insertAfter(this);
				setSpacerSize($(this).outerWidth(), $(this).outerHeight());
			},
			onDrag:function(e){
				var p = findPanel(e, this);
				if (p){
					if (p.pos == 'up'){
						spacer.insertBefore(p.target);
					} else {
						spacer.insertAfter(p.target);
					}
					setSpacerSize($(p.target).outerWidth());
				} else {
					var c = findColumn(e);
					if (c){
						if (c.find('div.portal-spacer').length == 0){
							spacer.appendTo(c);
							setSize(target);
							setSpacerSize(c.width());
						}
					}
				}
			},
			onStopDrag:function(e){
				$(this).css('position', 'static');
				$(this).show();
				spacer.hide();
				$(this).insertAfter(spacer);
				spacer.remove();
				setSize(target);
				panel.panel('move');

				var opts = $.data(target, 'portal').options;
				opts.onStateChange.call(target, panel);
			}
		});

		/**
		 * find which panel the cursor is over
		 */
		function findPanel(e, source){
			var result = null;
			$(target).find('div.portal-p').each(function(){
				var pal = $(this).panel('panel');
				if (pal[0] != source){
					var pos = pal.offset();
					if (e.pageX > pos.left && e.pageX < pos.left + pal.outerWidth()
							&& e.pageY > pos.top && e.pageY < pos.top + pal.outerHeight()){
						if (e.pageY > pos.top + pal.outerHeight() / 2){
							result = {
								target:pal,
								pos:'down'
							};
						} else {
							result = {
								target:pal,
								pos:'up'
							}
						}
					}
				}
			});
			return result;
		}

		/**
		 * find which portal column the cursor is over
		 */
		function findColumn(e){
			var result = null;
			$(target).find('div.portal-column').each(function(){
				var pal = $(this);
				var pos = pal.offset();
				if (e.pageX > pos.left && e.pageX < pos.left + pal.outerWidth()){
					result = pal;
				}
			});
			return result;
		}

		/**
		 * set the spacer size
		 */
		function setSpacerSize(width, height){
			spacer._outerWidth(width);
			if (height){
				spacer._outerHeight(height);
			}
		}
	}


	$.fn.portal = function(options, param){
		if (typeof options == 'string'){
			return $.fn.portal.methods[options](this, param);
		}

		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'portal');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'portal', {
					options: $.extend({}, $.fn.portal.defaults, $.fn.portal.parseOptions(this), options),
					columnWidths: init(this)
				});
			}
			if (state.options.border){
				$(this).removeClass('portal-noborder');
			} else {
				$(this).addClass('portal-noborder');
			}
			initCss();
			setSize(this);
		});
	};

	$.fn.portal.methods = {
		options: function(jq){
			return $.data(jq[0], 'portal').options;
		},
		resize: function(jq, param){
			return jq.each(function(){
				if (param){
					var opts = $.data(this, 'portal').options;
					if (param.width) opts.width = param.width;
					if (param.height) opts.height = param.height;
				}
				setSize(this);
			});
		},
		getPanels: function(jq, columnIndex){
			var c = jq;	// the panel container
			if (columnIndex >= 0){
				c = jq.find('div.portal-column:eq(' + columnIndex + ')');
			}
			var panels = [];
			c.find('div.portal-p').each(function(){
				panels.push($(this));
			});
			return panels;
		},
		add: function(jq, param){	// param: {panel,columnIndex}
			return jq.each(function(){
				var c = $(this).find('div.portal-column:eq(' + param.columnIndex + ')');
				var p = param.panel.addClass('portal-p');
				p.panel('panel').addClass('portal-panel').appendTo(c);
				makeDraggable(this, p);
				p.panel('resize', {width:c.width()});
			});
		},
		remove: function(jq, panel){
			return jq.each(function(){
				var panels = $(this).portal('getPanels');
				for(var i=0; i<panels.length; i++){
					var p = panels[i];
					if (p[0] == $(panel)[0]){
						p.panel('destroy');
					}
				}
			});
		},
		disableDragging: function(jq, panel){
			panel.panel('panel').draggable('disable');
			return jq;
		},
		enableDragging: function(jq, panel){
			panel.panel('panel').draggable('enable');
			return jq;
		}
	};

	$.fn.portal.parseOptions = function(target){
		var t = $(target);
		return {
			width: (parseInt(target.style.width) || undefined),
			height: (parseInt(target.style.height) || undefined),
			border: (t.attr('border') ? t.attr('border') == 'true' : undefined),
			fit: (t.attr('fit') ? t.attr('fit') == 'true' : undefined)
		};
	};

	$.fn.portal.defaults = {
		width:'auto',
		height:'auto',
		border:true,
		fit:false,
		onResize:function(width,height){},
		onStateChange:function(panel){}
	};
})(jqBiz);

/**
 * color - EasyUI Color extension
 */
(function($){
	$(function(){
		if (!$('#easyui-color-style').length){
			$('head').append(
				'<style id="easyui-color-style">' +
				'.color-cell{display:inline-block;float:left;cursor:pointer;border:1px solid #fff}' +
				'.color-cell:hover{border:1px solid #000}' +
				'</style>'
			);
		}
	});

	function create(target){
		var opts = $.data(target, 'color').options;
		$(target).combo($.extend({}, opts, {
			panelWidth: opts.cellWidth*8+2,
			panelHeight: opts.cellHeight*7+2,
			onShowPanel: function(){
				var p = $(this).combo('panel');
				if (p.is(':empty')){
					var colors = [
						"0,0,0","68,68,68","102,102,102","153,153,153","204,204,204","238,238,238","243,243,243","255,255,255",
						"244,204,204","252,229,205","255,242,204","217,234,211","208,224,227","207,226,243","217,210,233","234,209,220",
						"234,153,153","249,203,156","255,229,153","182,215,168","162,196,201","159,197,232","180,167,214","213,166,189",
						"224,102,102","246,178,107","255,217,102","147,196,125","118,165,175","111,168,220","142,124,195","194,123,160",
						"204,0,0","230,145,56","241,194,50","106,168,79","69,129,142","61,133,198","103,78,167","166,77,121",
						"153,0,0","180,95,6","191,144,0","56,118,29","19,79,92","11,83,148","53,28,117","116,27,71",
						"102,0,0","120,63,4","127,96,0","39,78,19","12,52,61","7,55,99","32,18,77","76,17,48"
					];
					for(var i=0; i<colors.length; i++){
						var a = $('<a class="color-cell"></a>').appendTo(p);
						a.css('backgroundColor', 'rgb('+colors[i]+')');
					}
					var cells = p.find('.color-cell');
					cells._outerWidth(opts.cellWidth)._outerHeight(opts.cellHeight);
					cells.bind('click.color', function(e){
						var color = $(this).css('backgroundColor');
						$(target).color('setValue', color);
						$(target).combo('hidePanel');
					});
				}
			}
		}));
		if (opts.value){
			$(target).color('setValue', opts.value);
		}
	}

	$.fn.color = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.color.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.combo(options, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'color');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'color', {
					options: $.extend({}, $.fn.color.defaults, $.fn.color.parseOptions(this), options)
				});
			}
			create(this);
		});
	};

	$.fn.color.methods = {
		options: function(jq){
			return jq.data('color').options;
		},
		setValue: function(jq, value){
			return jq.each(function(){
				var tb = $(this).combo('textbox').css('backgroundColor', value);
				value = tb.css('backgroundColor');
				if (value.indexOf('rgb') >= 0){
					var bg = value.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
					value = '#' + hex(bg[1]) + hex(bg[2]) + hex(bg[3]);
				}
				$(this).combo('setValue', value).combo('setText', value);

				function hex(x){
					return ('0'+parseInt(x).toString(16)).slice(-2);
				}
			})
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).combo('clear');
				$(this).combo('textbox').css('backgroundColor', '');
			});
		}
	};

	$.fn.color.parseOptions = function(target){
		return $.extend({}, $.fn.combo.parseOptions(target), {

		});
	};

	$.fn.color.defaults = $.extend({}, $.fn.combo.defaults, {
		editable: false,
		cellWidth: 20,
		cellHeight: 20
	});

	$.parser.plugins.push('color');
})(jqBiz);


/**
 * editable datagrid - jQuery EasyUI Editable DataGrid
 */
(function($){
	// var oldLoadDataMethod = $.fn.datagrid.methods.loadData;
	// $.fn.datagrid.methods.loadData = function(jq, data){
	// 	jq.each(function(){
	// 		$.data(this, 'datagrid').filterSource = null;
	// 	});
	// 	return oldLoadDataMethod.call($.fn.datagrid.methods, jq, data);
	// };

	var autoGrids = [];
	function checkAutoGrid(){
		autoGrids = $.grep(autoGrids, function(t){
			return t.length && t.data('edatagrid');
		});
	}
	function saveAutoGrid(omit){
		checkAutoGrid();
		$.map(autoGrids, function(t){
			if (t[0] != $(omit)[0]){
				t.edatagrid('saveRow');
			}
		});
		checkAutoGrid();
	}
	function addAutoGrid(dg){
		checkAutoGrid();
		for(var i=0; i<autoGrids.length; i++){
			if ($(autoGrids[i])[0] == $(dg)[0]){return;}
		}
		autoGrids.push($(dg));
	}
	function delAutoGrid(dg){
		checkAutoGrid();
		autoGrids = $.grep(autoGrids, function(t){
			return $(t)[0] != $(dg)[0];
		});
	}

	$(function(){
		$(document).unbind('.edatagrid').bind('mousedown.edatagrid', function(e){
			var p = $(e.target).closest('div.datagrid-view,div.combo-panel,div.window,div.window-mask');
			if (p.length){
				if (p.hasClass('datagrid-view')){
					saveAutoGrid(p.children('table'));
				}
				return;
			}
			saveAutoGrid();
		});
	});

	function buildGrid(target){
		var opts = $.data(target, 'edatagrid').options;
		$(target).datagrid($.extend({}, opts, {
			onDblClickCell:function(index,field,value){
				if (opts.editing){
					$(this).edatagrid('editRow', index);
					focusEditor(target, field);
				}
				if (opts.onDblClickCell){
					opts.onDblClickCell.call(target, index, field, value);
				}
			},
			onClickCell:function(index,field,value){
				// if (opts.editing && opts.editIndex >= 0){
				// 	$(this).edatagrid('editRow', index);
				// 	focusEditor(target, field);
				// }
				if (opts.editIndex >= 0){
					var dg = $(this);
					if (opts.editing){
						dg.edatagrid('editRow', index);
					} else {
						setTimeout(function(){
							dg.edatagrid('selectRow', opts.editIndex);
						}, 0);
					}
					focusEditor(target, field);
				}
				if (opts.onClickCell){
					opts.onClickCell.call(target, index, field, value);
				}
			},
			onBeforeEdit: function(index, row){
				if (opts.onBeforeEdit){
					if (opts.onBeforeEdit.call(target, index, row) == false){
						return false;
					}
				}
				if (opts.autoSave){
					addAutoGrid(this);
				}
				opts.originalRow = $.extend(true, [], row);
			},
			onAfterEdit: function(index, row){
				delAutoGrid(this);
				opts.editIndex = -1;
				var url = row.isNewRecord ? opts.saveUrl : opts.updateUrl;
				if (url){
					var changed = false;
					var fields = $(this).edatagrid('getColumnFields',true).concat($(this).edatagrid('getColumnFields'));
					for(var i=0; i<fields.length; i++){
						var field = fields[i];
						var col = $(this).edatagrid('getColumnOption', field);
						if (col.editor && opts.originalRow[field] != row[field]){
							changed = true;
							break;
						}
					}
					if (changed){
						opts.poster.call(target, url, row, function(data){
							if (data.isError){
								var originalRow = opts.originalRow;
								$(target).edatagrid('cancelRow',index);
								$(target).edatagrid('selectRow',index);
								$(target).edatagrid('editRow',index);
								opts.originalRow = originalRow;
								opts.onError.call(target, index, data);
								return;
							}
							data.isNewRecord = null;
							$(target).datagrid('updateRow', {
								index: index,
								row: data
							});
							if (opts.tree){
								var idValue = row[opts.idField||'id'];
								var t = $(opts.tree);
								var node = t.tree('find', idValue);
								if (node){
									node.text = row[opts.treeTextField];
									t.tree('update', node);
								} else {
									var pnode = t.tree('find', row[opts.treeParentField]);
									t.tree('append', {
										parent: (pnode ? pnode.target : null),
										data: [{id:idValue,text:row[opts.treeTextField]}]
									});
								}
							}
							opts.onSuccess.call(target, index, row);
							opts.onSave.call(target, index, row);
						}, function(data){
							opts.onError.call(target, index, data);
						});
					} else {
						opts.onSave.call(target, index, row);
					}
				} else {
					row.isNewRecord = false;
					opts.onSave.call(target, index, row);
				}
				if (opts.onAfterEdit) opts.onAfterEdit.call(target, index, row);
			},
			onCancelEdit: function(index, row){
				delAutoGrid(this);
				opts.editIndex = -1;
				if (row.isNewRecord) {
					$(this).datagrid('deleteRow', index);
				}
				if (opts.onCancelEdit) opts.onCancelEdit.call(target, index, row);
			},
			onBeforeLoad: function(param){
				if (opts.onBeforeLoad.call(target, param) == false){return false}
				$(this).edatagrid('cancelRow');
				if (opts.tree){
					var node = $(opts.tree).tree('getSelected');
					param[opts.treeParentField] = node ? node.id : undefined;
				}
			}
		}));



		if (opts.tree){
			$(opts.tree).tree({
				url: opts.treeUrl,
				onClick: function(node){
					$(target).datagrid('load');
				},
				onDrop: function(dest,source,point){
					var targetId = $(this).tree('getNode', dest).id;
					var data = {
						id:source.id,
						targetId:targetId,
						point:point
					};
					opts.poster.call(target, opts.treeDndUrl, data, function(result){
						$(target).datagrid('load');
					});
				}
			});
		}
	}

	function focusEditor(target, field){
		var opts = $(target).edatagrid('options');
		var t;
		var editor = $(target).datagrid('getEditor', {index:opts.editIndex,field:field});
		if (editor){
			t = editor.target;
		} else {
			var editors = $(target).datagrid('getEditors', opts.editIndex);
			if (editors.length){
				t = editors[0].target;
			}
		}
		if (t){
			var state = $(target).data('datagrid');
			var left = state.dc.body2._scrollLeft();
			if ($(t).hasClass('textbox-f')){
				$(t).textbox('textbox').focus();
			} else {
				$(t).focus();
			}
			state.dc.body2._scrollLeft(left);	// restore the scroll left
		}
	}

	$.fn.edatagrid = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.edatagrid.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.datagrid(options, param);
			}
		}

		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'edatagrid');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'edatagrid', {
					options: $.extend({}, $.fn.edatagrid.defaults, $.fn.edatagrid.parseOptions(this), options)
				});
			}
			buildGrid(this);
		});
	};

	$.fn.edatagrid.parseOptions = function(target){
		return $.extend({}, $.fn.datagrid.parseOptions(target), {
		});
	};

	$.fn.edatagrid.methods = {
		options: function(jq){
			var opts = $.data(jq[0], 'edatagrid').options;
			return opts;
		},
		loadData: function(jq, data){
			return jq.each(function(){
				$(this).edatagrid('cancelRow');
				$(this).datagrid('loadData', data);
			});
		},
		enableEditing: function(jq){
			return jq.each(function(){
				var opts = $.data(this, 'edatagrid').options;
				opts.editing = true;
			});
		},
		disableEditing: function(jq){
			return jq.each(function(){
				var opts = $.data(this, 'edatagrid').options;
				opts.editing = false;
			});
		},
		isEditing: function(jq, index){
			var opts = $.data(jq[0], 'edatagrid').options;
			var tr = opts.finder.getTr(jq[0], index);
			return tr.length && tr.hasClass('datagrid-row-editing');
		},
		editRow: function(jq, index){
			return jq.each(function(){
				var dg = $(this);
				var opts = $.data(this, 'edatagrid').options;
				var editIndex = opts.editIndex;
				if (editIndex != index){
					if (dg.datagrid('validateRow', editIndex)){
						if (editIndex>=0){
							if (opts.onBeforeSave.call(this, editIndex) == false) {
								setTimeout(function(){
									dg.datagrid('selectRow', editIndex);
								},0);
								return;
							}
						}
						dg.datagrid('endEdit', editIndex);
						dg.datagrid('beginEdit', index);
						if (!dg.edatagrid('isEditing', index)){
							return;
						}
						opts.editIndex = index;
						focusEditor(this);

						var rows = dg.datagrid('getRows');
						opts.onEdit.call(this, index, rows[index]);
					} else {
						setTimeout(function(){
							dg.datagrid('selectRow', editIndex);
						}, 0);
					}
				}
			});
		},
		addRow: function(jq, index){
			return jq.each(function(){
				var dg = $(this);
				var opts = $.data(this, 'edatagrid').options;
				if (opts.editIndex >= 0){
					if (!dg.datagrid('validateRow', opts.editIndex)){
						dg.datagrid('selectRow', opts.editIndex);
						return;
					}
					if (opts.onBeforeSave.call(this, opts.editIndex) == false){
						setTimeout(function(){
							dg.datagrid('selectRow', opts.editIndex);
						},0);
						return;
					}
					dg.datagrid('endEdit', opts.editIndex);
				}

				function _add(index, row){
					if (index == undefined){
						dg.datagrid('appendRow', row);
						opts.editIndex = dg.datagrid('getRows').length - 1;
					} else {
						dg.datagrid('insertRow', {index:index,row:row});
						opts.editIndex = index;
					}
				}
				if (typeof index == 'object'){
					_add(index.index, $.extend(index.row, {isNewRecord:true}))
				} else {
					_add(index, {isNewRecord:true});
				}

				dg.datagrid('beginEdit', opts.editIndex);
				dg.datagrid('selectRow', opts.editIndex);

				var rows = dg.datagrid('getRows');
				if (opts.tree){
					var node = $(opts.tree).tree('getSelected');
					rows[opts.editIndex][opts.treeParentField] = (node ? node.id : 0);
				}

				opts.onAdd.call(this, opts.editIndex, rows[opts.editIndex]);
			});
		},
		saveRow: function(jq){
			return jq.each(function(){
				var dg = $(this);
				var opts = $.data(this, 'edatagrid').options;
				if (opts.editIndex >= 0){
					if (opts.onBeforeSave.call(this, opts.editIndex) == false) {
						setTimeout(function(){
							dg.datagrid('selectRow', opts.editIndex);
						},0);
						return;
					}
					$(this).datagrid('endEdit', opts.editIndex);
				}
			});
		},
		cancelRow: function(jq){
			return jq.each(function(){
				var opts = $.data(this, 'edatagrid').options;
				if (opts.editIndex >= 0){
					$(this).datagrid('cancelEdit', opts.editIndex);
				}
			});
		},
		destroyRow: function(jq, index){
			return jq.each(function(){
				var dg = $(this);
				var opts = $.data(this, 'edatagrid').options;

				var rows = [];
				if (index == undefined){
					rows = dg.datagrid('getSelections');
				} else {
					var rowIndexes = $.isArray(index) ? index : [index];
					for(var i=0; i<rowIndexes.length; i++){
						var row = opts.finder.getRow(this, rowIndexes[i]);
						if (row){
							rows.push(row);
						}
					}
				}

				if (!rows.length){
					$.messager.show({
						title: opts.destroyMsg.norecord.title,
						msg: opts.destroyMsg.norecord.msg
					});
					return;
				}

				$.messager.confirm(opts.destroyMsg.confirm.title,opts.destroyMsg.confirm.msg,function(r){
					if (r){
						for(var i=0; i<rows.length; i++){
							_del(rows[i]);
						}
						dg.datagrid('clearSelections');
					}
				});

				function _del(row){
					var index = dg.datagrid('getRowIndex', row);
					if (index == -1){return}
					if (row.isNewRecord){
						dg.datagrid('cancelEdit', index);
					} else {
						if (opts.destroyUrl){
							var idValue = row[opts.idField||'id'];
							opts.poster.call(dg[0], opts.destroyUrl, {id:idValue}, function(data){
								var index = dg.datagrid('getRowIndex', idValue);
								if (data.isError){
									dg.datagrid('selectRow', index);
									opts.onError.call(dg[0], index, data);
									return;
								}
								if (opts.tree){
									dg.datagrid('reload');
									var t = $(opts.tree);
									var node = t.tree('find', idValue);
									if (node){
										t.tree('remove', node.target);
									}
								} else {
									dg.datagrid('cancelEdit', index);
									dg.datagrid('deleteRow', index);
								}
								opts.onDestroy.call(dg[0], index, $.extend(row,data));
								var pager = dg.datagrid('getPager');
								if (pager.length && !dg.datagrid('getRows').length){
									dg.datagrid('options').pageNumber = pager.pagination('options').pageNumber;
									dg.datagrid('reload');
								}
							}, function(data){
								opts.onError.call(dg[0], index, data);
							});
						} else {
							dg.datagrid('cancelEdit', index);
							dg.datagrid('deleteRow', index);
							opts.onDestroy.call(dg[0], index, row);
						}
					}
				}
			});
		}
	};

	$.fn.edatagrid.defaults = $.extend({}, $.fn.datagrid.defaults, {
		singleSelect: true,
		editing: true,
		editIndex: -1,
		destroyMsg:{
			norecord:{
				title:'Warning',
				msg:'No record is selected.'
			},
			confirm:{
				title:'Confirm',
				msg:'Are you sure you want to delete?'
			}
		},
		poster: function(url, data, success, error){
			$.ajax({
				type: 'post',
				url: url,
				data: data,
				dataType: 'json',
				success: function(data){
					success(data);
				},
				error: function(jqXHR, textStatus, errorThrown){
					error({
						jqXHR: jqXHR,
						textStatus: textStatus,
						errorThrown: errorThrown
					});
				}
			});
		},

		autoSave: false,	// auto save the editing row when click out of datagrid
		url: null,	// return the datagrid data
		saveUrl: null,	// return the added row
		updateUrl: null,	// return the updated row
		destroyUrl: null,	// return {success:true}

		tree: null,		// the tree selector
		treeUrl: null,	// return tree data
		treeDndUrl: null,	// to process the drag and drop operation, return {success:true}
		treeTextField: 'name',
		treeParentField: 'parentId',

		onAdd: function(index, row){},
		onEdit: function(index, row){},
		onBeforeSave: function(index){},
		onSave: function(index, row){},
		onSuccess: function(index, row){},
		onDestroy: function(index, row){},
		onError: function(index, row){}
	});

	////////////////////////////////
	$.parser.plugins.push('edatagrid');
})(jqBiz);

/**
 * datagrid-filter - jQuery EasyUI Datagrid Filter
 */
(function($){
	function getPluginName(target){
		if ($(target).data('treegrid')){
			return 'treegrid';
		} else {
			return 'datagrid';
		}
	}

	var autoSizeColumn1 = $.fn.datagrid.methods.autoSizeColumn;
	var loadDataMethod1 = $.fn.datagrid.methods.loadData;
	var appendMethod1 = $.fn.datagrid.methods.appendRow;
	var deleteMethod1 = $.fn.datagrid.methods.deleteRow;
	$.extend($.fn.datagrid.methods, {
		autoSizeColumn: function(jq, field){
			return jq.each(function(){
				var fc = $(this).datagrid('getPanel').find('.datagrid-header .datagrid-filter-c');
				// fc.hide();
				fc.css({
					width:'1px',
					height:0
				});
				autoSizeColumn1.call($.fn.datagrid.methods, $(this), field);
				// fc.show();
				fc.css({
					width:'',
					height:''
				});
				resizeFilter(this, field);
			});
		},
		loadData: function(jq, data){
			jq.each(function(){
				$.data(this, 'datagrid').filterSource = null;
			});
			return loadDataMethod1.call($.fn.datagrid.methods, jq, data);
		},
		appendRow: function(jq, row){
			var result = appendMethod1.call($.fn.datagrid.methods, jq, row);
			jq.each(function(){
				var state = $(this).data('datagrid');
				if (state.filterSource){
					state.filterSource.total++;
					if (state.filterSource.rows != state.data.rows){
						state.filterSource.rows.push(row);
					}
				}
			});
			return result;
		},
		deleteRow: function(jq, index){
			jq.each(function(){
				var state = $(this).data('datagrid');
				var opts = state.options;
				if (state.filterSource && opts.idField){
					if (state.filterSource.rows == state.data.rows){
						state.filterSource.total--;
					} else {
						for(var i=0; i<state.filterSource.rows.length; i++){
							var row = state.filterSource.rows[i];
							if (row[opts.idField] == state.data.rows[index][opts.idField]){
								state.filterSource.rows.splice(i,1);
								state.filterSource.total--;
								break;
							}
						}
					}
				}
			});
			return deleteMethod1.call($.fn.datagrid.methods, jq, index);
		}
	});

	var loadDataMethod2 = $.fn.treegrid.methods.loadData;
	var appendMethod2 = $.fn.treegrid.methods.append;
	var insertMethod2 = $.fn.treegrid.methods.insert;
	var removeMethod2 = $.fn.treegrid.methods.remove;
	$.extend($.fn.treegrid.methods, {
		loadData: function(jq, data){
			jq.each(function(){
				$.data(this, 'treegrid').filterSource = null;
			});
			return loadDataMethod2.call($.fn.treegrid.methods, jq, data);
		},
		append: function(jq, param){
			return jq.each(function(){
				var state = $(this).data('treegrid');
				var opts = state.options;
				if (opts.oldLoadFilter){
					var rows = translateTreeData(this, param.data, param.parent);
					state.filterSource.total += rows.length;
					state.filterSource.rows = state.filterSource.rows.concat(rows);
					$(this).treegrid('loadData', state.filterSource)
				} else {
					appendMethod2($(this), param);
				}
			});
		},
		insert: function(jq, param){
			return jq.each(function(){
				var state = $(this).data('treegrid');
				var opts = state.options;
				if (opts.oldLoadFilter){
					var ref = param.before || param.after;
					var index = getNodeIndex(param.before || param.after);
					var pid = index>=0 ? state.filterSource.rows[index]._parentId : null;
					var rows = translateTreeData(this, [param.data], pid);
					var newRows = state.filterSource.rows.splice(0, index>=0 ? (param.before ? index : index+1) : (state.filterSource.rows.length));
					newRows = newRows.concat(rows);
					newRows = newRows.concat(state.filterSource.rows);
					state.filterSource.total += rows.length;
					state.filterSource.rows = newRows;
					$(this).treegrid('loadData', state.filterSource);

					function getNodeIndex(id){
						var rows = state.filterSource.rows;
						for(var i=0; i<rows.length; i++){
							if (rows[i][opts.idField] == id){
								return i;
							}
						}
						return -1;
					}
				} else {
					insertMethod2($(this), param);
				}
			});
		},
		remove: function(jq, id){
			jq.each(function(){
				var state = $(this).data('treegrid');
				if (state.filterSource){
					var opts = state.options;
					var rows = state.filterSource.rows;
					for(var i=0; i<rows.length; i++){
						if (rows[i][opts.idField] == id){
							rows.splice(i, 1);
							state.filterSource.total--;
							break;
						}
					}
				}
			});
			return removeMethod2(jq, id);
		}
	});

	var extendedOptions = {
		filterMenuIconCls: 'icon-ok',
		filterBtnIconCls: 'icon-filter',
		filterBtnPosition: 'right',
		filterPosition: 'bottom',
		remoteFilter: false,
		clientPaging: true,
		showFilterBar: true,
		filterDelay: 400,
		filterRules: [],
		// specify whether the filtered records need to match ALL or ANY of the applied filters
		filterMatchingType: 'all',	// possible values: 'all','any'
		filterIncludingChild: false,
		// filterCache: {},
		filterMatcher: function(data){
			var name = getPluginName(this);
			var dg = $(this);
			var state = $.data(this, name);
			var opts = state.options;
			if (opts.filterRules.length){
				var rows = [];
				if (name == 'treegrid'){
					var rr = {};
					$.map(data.rows, function(row){
						if (isMatch(row, row[opts.idField])){
							rr[row[opts.idField]] = row;
							var prow = getRow(data.rows, row._parentId);
							while(prow){
								rr[prow[opts.idField]] = prow;
								prow = getRow(data.rows, prow._parentId);
							}
							if (opts.filterIncludingChild){
								var cc = getAllChildRows(data.rows, row[opts.idField]);
								$.map(cc, function(row){
									rr[row[opts.idField]] = row;
								});
							}
						}
					});
					for(var id in rr){
						rows.push(rr[id]);
					}
				} else {
					for(var i=0; i<data.rows.length; i++){
						var row = data.rows[i];
						if (isMatch(row, i)){
							rows.push(row);
						}
					}
				}
				data = {
					total: data.total - (data.rows.length - rows.length),
					rows: rows
				};
			}
			return data;

			function isMatch(row, index){
				if (opts.val == $.fn.combogrid.defaults.val){
					opts.val = extendedOptions.val;
				}
				var rules = opts.filterRules;
				if (!rules.length){return true;}
				for(var i=0; i<rules.length; i++){
					var rule = rules[i];

					// var source = row[rule.field];
					// var col = dg.datagrid('getColumnOption', rule.field);
					// if (col && col.formatter){
					// 	source = col.formatter(row[rule.field], row, index);
					// }

					var col = dg.datagrid('getColumnOption', rule.field);
					var formattedValue = (col && col.formatter) ? col.formatter(row[rule.field], row, index) : undefined;
					var source = opts.val.call(dg[0], row, rule.field, formattedValue);

					if (source == undefined){
						source = '';
					}
					var op = opts.operators[rule.op];
					var matched = op.isMatch(source, rule.value);
					if (opts.filterMatchingType == 'any'){
						if (matched){return true;}
					} else {
						if (!matched){return false;}
					}
				}
				return opts.filterMatchingType == 'all';
			}
			function getRow(rows, id){
				for(var i=0; i<rows.length; i++){
					var row = rows[i];
					if (row[opts.idField] == id){
						return row;
					}
				}
				return null;
			}
			function getAllChildRows(rows, id){
				var cc = getChildRows(rows, id);
				var stack = $.extend(true, [], cc);
				while(stack.length){
					var row = stack.shift();
					var c2 = getChildRows(rows, row[opts.idField]);
					cc = cc.concat(c2);
					stack = stack.concat(c2);
				}
				return cc;
			}
			function getChildRows(rows, id){
				var cc = [];
				for(var i=0; i<rows.length; i++){
					var row = rows[i];
					if (row._parentId == id){
						cc.push(row);
					}
				}
				return cc;
			}
		},
		defaultFilterType: 'text',
		defaultFilterOperator: 'contains',
		defaultFilterOptions: {
			onInit: function(target){
				var name = getPluginName(target);
				var opts = $(target)[name]('options');
				var filterOpts = this.filterOptions;
				var field = $(this).attr('name');
				var input = $(this);
				if (input.data('textbox')){
					input = input.textbox('textbox');
				}
				input.unbind('.filter').bind('keydown.filter', function(e){
					var t = $(this);
					if (this.timer){
						clearTimeout(this.timer);
					}
					if (e.keyCode == 13){
						_doFilter();
					} else if (opts.filterDelay){
						this.timer = setTimeout(function(){
							_doFilter();
						}, opts.filterDelay);
					}
				});
				function _doFilter(){
					var rule = $(target)[name]('getFilterRule', field);
					var value = input.val();
					if (value != ''){
						if ((rule && rule.value!=value) || !rule){
							var op = rule ? rule.op : (filterOpts ? filterOpts.defaultFilterOperator||opts.defaultFilterOperator : opts.defaultFilterOperator);
							$(target)[name]('addFilterRule', {
								field: field,
								// op: opts.defaultFilterOperator,
								op: op,
								value: value
							});
							$(target)[name]('doFilter');
						}
					} else {
						if (rule){
							$(target)[name]('removeFilterRule', field);
							$(target)[name]('doFilter');
						}
					}
				}
			}
		},
		filterStringify: function(data){
			return JSON.stringify(data);
		},
		// the function to retrieve the field value of a row to match the filter rule
		val: function(row, field, formattedValue){
			return formattedValue || row[field];
		},
		onClickMenu: function(item,button){}
	};
	$.extend($.fn.datagrid.defaults, extendedOptions);
	$.extend($.fn.treegrid.defaults, extendedOptions);

	// filter types
	$.fn.datagrid.defaults.filters = $.extend({}, $.fn.datagrid.defaults.editors, {
		label: {
			init: function(container, options){
				return $('<span></span>').appendTo(container);
			},
			getValue: function(target){
				return $(target).html();
			},
			setValue: function(target, value){
				$(target).html(value);
			},
			resize: function(target, width){
				$(target)._outerWidth(width)._outerHeight(22);
			}
		}
	});
	$.fn.treegrid.defaults.filters = $.fn.datagrid.defaults.filters;

	// filter operators
	$.fn.datagrid.defaults.operators = {
		nofilter: {
			text: 'No Filter'
		},
		contains: {
			text: 'Contains',
			isMatch: function(source, value){
				source = String(source);
				value = String(value);
				return source.toLowerCase().indexOf(value.toLowerCase()) >= 0;
			}
		},
		equal: {
			text: 'Equal',
			isMatch: function(source, value){
				return source == value;
			}
		},
		notequal: {
			text: 'Not Equal',
			isMatch: function(source, value){
				return source != value;
			}
		},
		beginwith: {
			text: 'Begin With',
			isMatch: function(source, value){
				source = String(source);
				value = String(value);
				return source.toLowerCase().indexOf(value.toLowerCase()) == 0;
			}
		},
		endwith: {
			text: 'End With',
			isMatch: function(source, value){
				source = String(source);
				value = String(value);
				return source.toLowerCase().indexOf(value.toLowerCase(), source.length - value.length) !== -1;
			}
		},
		less: {
			text: 'Less',
			isMatch: function(source, value){
				return source < value;
			}
		},
		lessorequal: {
			text: 'Less Or Equal',
			isMatch: function(source, value){
				return source <= value;
			}
		},
		greater: {
			text: 'Greater',
			isMatch: function(source, value){
				return source > value;
			}
		},
		greaterorequal: {
			text: 'Greater Or Equal',
			isMatch: function(source, value){
				return source >= value;
			}
		}
	};
	$.fn.treegrid.defaults.operators = $.fn.datagrid.defaults.operators;

	function resizeFilter(target, field){
		var toFixColumnSize = false;
		var dg = $(target);
		var header = dg.datagrid('getPanel').find('div.datagrid-header');
		var tr = header.find('.datagrid-header-row:not(.datagrid-filter-row)');
		var ff = field ? header.find('.datagrid-filter[name="'+field+'"]') : header.find('.datagrid-filter');
		ff.each(function(){
			var name = $(this).attr('name');
			var col = dg.datagrid('getColumnOption', name);
			var cc = $(this).closest('div.datagrid-filter-c');
			var btn = cc.find('a.datagrid-filter-btn');
			var cell = tr.find('td[field="'+name+'"] .datagrid-cell');
			var cellWidth = cell._outerWidth();
			if (cellWidth != _getContentWidth(cc)){
				this.filter.resize(this, cellWidth - btn._outerWidth());
			}
			if (cc.width() > col.boxWidth+col.deltaWidth-1){
				col.boxWidth = cc.width() - col.deltaWidth + 1;
				col.width = col.boxWidth + col.deltaWidth;
				toFixColumnSize = true;
			}
		});
		if (toFixColumnSize){
			$(target).datagrid('fixColumnSize');
		}

		function _getContentWidth(cc){
			var w = 0;
			$(cc).children(':visible').each(function(){
				w += $(this)._outerWidth();
			});
			return w;
		}
	}

	function getFilterComponent(target, field){
		var header = $(target).datagrid('getPanel').find('div.datagrid-header');
		return header.find('tr.datagrid-filter-row td[field="'+field+'"] .datagrid-filter');
	}

	/**
	 * get filter rule index, return -1 if not found.
	 */
	function getRuleIndex(target, field){
		var name = getPluginName(target);
		var rules = $(target)[name]('options').filterRules;
		for(var i=0; i<rules.length; i++){
			if (rules[i].field == field){
				return i;
			}
		}
		return -1;
	}

	function getFilterRule(target, field){
		var name = getPluginName(target);
		var rules = $(target)[name]('options').filterRules;
		var index = getRuleIndex(target, field);
		if (index >= 0){
			return rules[index];
		} else {
			return null;
		}
	}

	function addFilterRule(target, param){
		var name = getPluginName(target);
		var opts = $(target)[name]('options');
		var rules = opts.filterRules;

		if (param.op == 'nofilter'){
			removeFilterRule(target, param.field);
		} else {
			var index = getRuleIndex(target, param.field);
			if (index >= 0){
				$.extend(rules[index], param);
			} else {
				rules.push(param);
			}
		}

		var input = getFilterComponent(target, param.field);
		if (input.length){
			if (param.op != 'nofilter'){
				var value = input.val();
				if (input.data('textbox')){
					value = input.textbox('getText');
				}
				if (value != param.value){
					input[0].filter.setValue(input, param.value);
				}
			}
			var menu = input[0].menu;
			if (menu){
				menu.find('.'+opts.filterMenuIconCls).removeClass(opts.filterMenuIconCls);
				var item = menu.menu('findItem', opts.operators[param.op]['text']);
				menu.menu('setIcon', {
					target: item.target,
					iconCls: opts.filterMenuIconCls
				});
			}
		}
	}

	function removeFilterRule(target, field){
		var name = getPluginName(target);
		var dg = $(target);
		var opts = dg[name]('options');
		if (field){
			var index = getRuleIndex(target, field);
			if (index >= 0){
				opts.filterRules.splice(index, 1);
			}
			_clear([field]);
		} else {
			opts.filterRules = [];
			var fields = dg.datagrid('getColumnFields',true).concat(dg.datagrid('getColumnFields'));
			_clear(fields);
		}

		function _clear(fields){
			for(var i=0; i<fields.length; i++){
				var input = getFilterComponent(target, fields[i]);
				if (input.length){
					input[0].filter.setValue(input, '');
					var menu = input[0].menu;
					if (menu){
						menu.find('.'+opts.filterMenuIconCls).removeClass(opts.filterMenuIconCls);
					}
				}
			}
		}
	}

	function doFilter(target){
		var name = getPluginName(target);
		var state = $.data(target, name);
		var opts = state.options;
		if (opts.remoteFilter){
			$(target)[name]('load');
		} else {
			if (opts.view.type == 'scrollview' && state.data.firstRows && state.data.firstRows.length){
				state.data.rows = state.data.firstRows;
			}
			$(target)[name]('getPager').pagination('refresh', {pageNumber:1});
			$(target)[name]('options').pageNumber = 1;
			$(target)[name]('loadData', state.filterSource || state.data);
		}
	}

	function translateTreeData(target, children, pid){
		var opts = $(target).treegrid('options');
		if (!children || !children.length){return []}
		var rows = [];
		$.map(children, function(item){
			item._parentId = pid;
			rows.push(item);
			rows = rows.concat(translateTreeData(target, item.children, item[opts.idField]));
		});
		$.map(rows, function(row){
			row.children = undefined;
		});
		return rows;
	}

	function myLoadFilter(data, parentId){
		var target = this;
		var name = getPluginName(target);
		var state = $.data(target, name);
		var opts = state.options;

		if (name == 'datagrid' && $.isArray(data)){
			data = {
				total: data.length,
				rows: data
			};
		} else if (name == 'treegrid' && $.isArray(data)){
			var rows = translateTreeData(target, data, parentId);
			data = {
				total: rows.length,
				rows: rows
			}
		}
		if (!opts.remoteFilter || opts.clientPaging){
			if (!state.filterSource){
				state.filterSource = data;
			} else {
				if (!opts.isSorting) {
					if (name == 'datagrid'){
						state.filterSource = data;
					} else {
						state.filterSource.total += data.length;
						state.filterSource.rows = state.filterSource.rows.concat(data.rows);
						if (parentId){
							return opts.filterMatcher.call(target, data);
						}
					}
				} else {
					opts.isSorting = undefined;
				}
			}
			if (!opts.remoteSort && opts.sortName){
				var names = opts.sortName.split(',');
				var orders = opts.sortOrder.split(',');
				var dg = $(target);
				state.filterSource.rows.sort(function(r1,r2){
					var r = 0;
					for(var i=0; i<names.length; i++){
						var sn = names[i];
						var so = orders[i];
						var col = dg.datagrid('getColumnOption', sn);
						var sortFunc = col.sorter || function(a,b){
							return a==b ? 0 : (a>b?1:-1);
						};
						r = sortFunc(r1[sn], r2[sn]) * (so=='asc'?1:-1);
						if (r != 0){
							return r;
						}
					}
					return r;
				});
			}
			data = opts.filterMatcher.call(target, {
				total: state.filterSource.total,
				rows: state.filterSource.rows,
				footer: state.filterSource.footer||[]
			});
		}
		if (opts.pagination && opts.clientPaging){
			var dg = $(target);
			var pager = dg[name]('getPager');
			pager.pagination({
				onSelectPage:function(pageNum, pageSize){
					opts.pageNumber = pageNum;
					opts.pageSize = pageSize;
					pager.pagination('refresh',{
						pageNumber:pageNum,
						pageSize:pageSize
					});
					// dg[name]('loadData', state.filterSource);
					if (opts.clientPaging){
						dg[name]('loadData', state.filterSource);
					} else {
						dg[name]('reload');
					}
				},
				onBeforeRefresh:function(){
					dg[name]('reload');
					return false;
				}
			});
			if (name == 'datagrid'){
				var pd = getPageData(data.rows);
				opts.pageNumber = pd.pageNumber;
				data.rows = pd.rows;
			} else {
				var topRows = [];
				var childRows = [];
				$.map(data.rows, function(row){
					row._parentId ? childRows.push(row) : topRows.push(row);
				});
				data.total = topRows.length;
				var pd = getPageData(topRows);
				opts.pageNumber = pd.pageNumber;
				data.rows = pd.rows.concat(childRows);
			}
		}
		$.map(data.rows, function(row){
			row.children = undefined;
		});
		return data;

		function getPageData(dataRows){
			var rows = [];
			var page = opts.pageNumber;
			while(page > 0){
				var start = (page-1)*parseInt(opts.pageSize);
				var end = start + parseInt(opts.pageSize);
				rows = dataRows.slice(start, end);
				if (rows.length){
					break;
				}
				page--;
			}
			return {
				pageNumber: page>0?page:1,
				rows: rows
			};
		}
	}

	function init(target, filters){
		filters = filters || [];
		var name = getPluginName(target);
		var state = $.data(target, name);
		var opts = state.options;
		if (!opts.filterRules.length){
			opts.filterRules = [];
		}
		opts.filterCache = opts.filterCache || {};
		var dgOpts = $.data(target, 'datagrid').options;

		var onResize = dgOpts.onResize;
		dgOpts.onResize = function(width,height){
			resizeFilter(target);
			onResize.call(this, width, height);
		}
		var onBeforeSortColumn = dgOpts.onBeforeSortColumn;
		dgOpts.onBeforeSortColumn = function(sort, order){
			var result = onBeforeSortColumn.call(this, sort, order);
			if (result != false){
				opts.isSorting = true;
			}
			return result;
		};

		var onResizeColumn = opts.onResizeColumn;
		opts.onResizeColumn = function(field,width){
			var fc = $(this).datagrid('getPanel').find('.datagrid-header .datagrid-filter-c');
			var focusOne = fc.find('.datagrid-filter:focus');
			// fc.hide();
			fc.css({
				width:'1px',
				height:0
			});
			$(target).datagrid('fitColumns');
			if (opts.fitColumns){
				resizeFilter(target);
			} else {
				resizeFilter(target, field);
			}
			// fc.show();
			fc.css({
				width:'',
				height:''
			});
			focusOne.blur().focus();
			onResizeColumn.call(target, field, width);
		};
		var onBeforeLoad = opts.onBeforeLoad;
		opts.onBeforeLoad = function(param1, param2){
			if (param1){
				param1.filterRules = opts.filterStringify(opts.filterRules);
			}
			if (param2){
				param2.filterRules = opts.filterStringify(opts.filterRules);
			}
			var result = onBeforeLoad.call(this, param1, param2);
			if (result != false && opts.url){
				if (name == 'datagrid'){
					state.filterSource = null;
				} else if (name == 'treegrid' && state.filterSource){
					if (param1){
						var id = param1[opts.idField];	// the id of the expanding row
						var rows = state.filterSource.rows || [];
						for(var i=0; i<rows.length; i++){
							if (id == rows[i]._parentId){	// the expanding row has children
								return false;
							}
						}
					} else {
						state.filterSource = null;
					}
				}
			}
			return result;
		};

		// opts.loadFilter = myLoadFilter;
		opts.loadFilter = function(data, parentId){
			var d = opts.oldLoadFilter.call(this, data, parentId);
			return myLoadFilter.call(this, d, parentId);
		};
		state.dc.view2.children('.datagrid-header').unbind('.filter').bind('focusin.filter', function(e){
			var header = $(this);
			setTimeout(function(){
				state.dc.body2._scrollLeft(header._scrollLeft());
			},0);
		});

		initCss();
		createFilter(true);
		createFilter();
		if (opts.fitColumns){
			setTimeout(function(){
				resizeFilter(target);
			}, 0);
		}

		$.map(opts.filterRules, function(rule){
			addFilterRule(target, rule);
		});

		function initCss(){
			if (!$('#datagrid-filter-style').length){
				$('head').append(
					'<style id="datagrid-filter-style">' +
					'a.datagrid-filter-btn{display:inline-block;width:22px;height:100%;margin:0;vertical-align:middle;cursor:pointer;opacity:0.6;filter:alpha(opacity=60);}' +
					'a:hover.datagrid-filter-btn{opacity:1;filter:alpha(opacity=100);}' +
					'.datagrid-filter-row .textbox,.datagrid-filter-row .textbox .textbox-text{-moz-border-radius:0;-webkit-border-radius:0;border-radius:0;}' +
					'.datagrid-filter-row input{margin:0;-moz-border-radius:0;-webkit-border-radius:0;border-radius:0;}' +
					'.datagrid-filter-c{overflow:hidden}' +
					'.datagrid-filter-cache{position:absolute;width:10px;height:10px;left:-99999px;}' +
					'</style>'
				);
			}
		}

		/**
		 * create filter component
		 */
		function createFilter(frozen){
			var dc = state.dc;
			var fields = $(target).datagrid('getColumnFields', frozen);
			if (frozen && opts.rownumbers){
				fields.unshift('_');
			}
			var table = (frozen?dc.header1:dc.header2).find('table.datagrid-htable');

			// clear the old filter component
			table.find('.datagrid-filter').each(function(){
				if (this.filter.destroy){
					this.filter.destroy(this);
				}
				if (this.menu){
					$(this.menu).menu('destroy');
				}
			});
			table.find('tr.datagrid-filter-row').remove();

			var tr = $('<tr class="datagrid-header-row datagrid-filter-row"></tr>');
			if (opts.filterPosition == 'bottom'){
				tr.appendTo(table.find('tbody'));
			} else {
				tr.prependTo(table.find('tbody'));
			}
			if (!opts.showFilterBar){
				tr.hide();
			}

			for(var i=0; i<fields.length; i++){
				var field = fields[i];
				var col = $(target).datagrid('getColumnOption', field);
				var td = $('<td></td>').attr('field', field).appendTo(tr);
				if (col && col.hidden){
					td.hide();
				}
				if (field == '_'){
					continue;
				}
				if (col && (col.checkbox || col.expander)){
					continue;
				}

				var fopts = getFilter(field);
				if (fopts){
					$(target)[name]('destroyFilter', field);	// destroy the old filter component
				} else {
					fopts = $.extend({}, {
						field: field,
						type: opts.defaultFilterType,
						options: opts.defaultFilterOptions
					});
				}

				var div = opts.filterCache[field];
				if (!div){
					div = $('<div class="datagrid-filter-c"></div>').appendTo(td);
					var filter = opts.filters[fopts.type];
					var input = filter.init(div, $.extend({height:opts.editorHeight},fopts.options||{}));
					input.addClass('datagrid-filter').attr('name', field);
					input[0].filter = filter;
					input[0].filterOptions = fopts;
					input[0].menu = createFilterButton(div, fopts.op);
					if (fopts.op && fopts.op.length){
						if (fopts.options && fopts.options.onInit){
							fopts.options.onInit.call(input[0], target);
						} else if (fopts.defaultFilterOperator){
							opts.defaultFilterOptions.onInit.call(input[0], target);
						}
					} else {
						opts.defaultFilterOptions.onInit.call(input[0], target);
					}
					// if (fopts.options){
					// 	if (fopts.options.onInit){
					// 		fopts.options.onInit.call(input[0], target);
					// 	}
					// } else {
					// 	opts.defaultFilterOptions.onInit.call(input[0], target);
					// }
					opts.filterCache[field] = div;
					resizeFilter(target, field);
				} else {
					div.appendTo(td);
				}
			}
		}

		function createFilterButton(container, operators){
			if (!operators){return null;}

			var btn = $('<a class="datagrid-filter-btn">&nbsp;</a>').addClass(opts.filterBtnIconCls);
			btn.css('height',opts.editorHeight);
			if (opts.filterBtnPosition == 'right'){
				btn.appendTo(container);
			} else {
				btn.prependTo(container);
			}

			var menu = $('<div></div>').appendTo('body');
			$.map(['nofilter'].concat(operators), function(item){
				var op = opts.operators[item];
				if (op){
					$('<div></div>').attr('name', item).html(op.text).appendTo(menu);
				}
			});
			menu.menu({
				alignTo:btn,
				onClick:function(item){
					var btn = $(this).menu('options').alignTo;
					var td = btn.closest('td[field]');
					var field = td.attr('field');
					var input = td.find('.datagrid-filter');
					var value = input[0].filter.getValue(input);

					if (opts.onClickMenu.call(target, item, btn, field) == false){
						return;
					}

					addFilterRule(target, {
						field: field,
						op: item.name,
						value: value
					});

					doFilter(target);
				}
			});

			btn[0].menu = menu;
			btn.bind('click', {menu:menu}, function(e){
				$(this.menu).menu('show');
				return false;
			});
			return menu;
		}

		function getFilter(field){
			for(var i=0; i<filters.length; i++){
				var filter = filters[i];
				if (filter.field == field){
					return filter;
				}
			}
			return null;
		}
	}

	$.extend($.fn.datagrid.methods, {
		isFilterEnabled: function(jq){
			var name = getPluginName(jq[0]);
			var opts = $.data(jq[0], name).options;
			if (opts.oldLoadFilter){
				return true;
			} else {
				return false;
			}
		},
		enableFilter: function(jq, filters){
			return jq.each(function(){
				var name = getPluginName(this);
				var opts = $.data(this, name).options;
				if (opts.oldLoadFilter){
					if (filters){
						$(this)[name]('disableFilter');
					} else {
						return;
					}
				}
				opts.oldLoadFilter = opts.loadFilter;
				init(this, filters);
				$(this)[name]('resize');
				if (opts.filterRules.length){
					if (opts.remoteFilter){
						doFilter(this);
					} else if (opts.data){
						doFilter(this);
					}
				}
			});
		},
		disableFilter: function(jq){
			return jq.each(function(){
				var name = getPluginName(this);
				var state = $.data(this, name);
				var opts = state.options;
				if (!opts.oldLoadFilter){
					return;
				}
				var dc = $(this).data('datagrid').dc;
				var div = dc.view.children('.datagrid-filter-cache');
				if (!div.length){
					div = $('<div class="datagrid-filter-cache"></div>').appendTo(dc.view);
				}
				for(var field in opts.filterCache){
					$(opts.filterCache[field]).appendTo(div);
				}
				var data = state.data;
				if (state.filterSource){
					data = state.filterSource;
					$.map(data.rows, function(row){
						row.children = undefined;
					});
				}
				dc.header1.add(dc.header2).find('tr.datagrid-filter-row').remove();
				opts.loadFilter = opts.oldLoadFilter || undefined;
				opts.oldLoadFilter = null;
				$(this)[name]('resize');
				$(this)[name]('loadData', data);

				// $(this)[name]({
				// 	data: data,
				// 	loadFilter: (opts.oldLoadFilter||undefined),
				// 	oldLoadFilter: null
				// });
			});
		},
		destroyFilter: function(jq, field){
			return jq.each(function(){
				var name = getPluginName(this);
				var state = $.data(this, name);
				var opts = state.options;
				if (field){
					_destroy(field);
				} else {
					for(var f in opts.filterCache){
						_destroy(f);
					}
					$(this).datagrid('getPanel').find('.datagrid-header .datagrid-filter-row').remove();
					$(this).data('datagrid').dc.view.children('.datagrid-filter-cache').remove();
					opts.filterCache = {};
					$(this)[name]('resize');
					$(this)[name]('disableFilter');
				}

				function _destroy(field){
					var c = $(opts.filterCache[field]);
					var input = c.find('.datagrid-filter');
					if (input.length){
						var filter = input[0].filter;
						if (filter.destroy){
							filter.destroy(input[0]);
						}
					}
					c.find('.datagrid-filter-btn').each(function(){
						$(this.menu).menu('destroy');
					});
					c.remove();
					opts.filterCache[field] = undefined;
				}
			});
		},
		getFilterRule: function(jq, field){
			return getFilterRule(jq[0], field);
		},
		addFilterRule: function(jq, param){
			return jq.each(function(){
				addFilterRule(this, param);
			});
		},
		removeFilterRule: function(jq, field){
			return jq.each(function(){
				removeFilterRule(this, field);
			});
		},
		doFilter: function(jq){
			return jq.each(function(){
				doFilter(this);
			});
		},
		getFilterComponent: function(jq, field){
			return getFilterComponent(jq[0], field);
		},
		resizeFilter: function(jq, field){
			return jq.each(function(){
				resizeFilter(this, field);
			});
		}
	});
})(jqBiz);

/**
 * datagrid-drag and drop - jQuery EasyUI Datagrid Drag-n-Drop Rows
 */
(function($){
	$.extend($.fn.datagrid.defaults, {
		dropAccept: 'tr.datagrid-row',
		dragSelection: false,
		dragDelay: 100,
		onBeforeDrag: function(row){},	// return false to deny drag
		onStartDrag: function(row){},
		onStopDrag: function(row){},
		onDragEnter: function(targetRow, sourceRow){},	// return false to deny drop
		onDragOver: function(targetRow, sourceRow){},	// return false to deny drop
		onDragLeave: function(targetRow, sourceRow){},
		onBeforeDrop: function(targetRow, sourceRow, point){},
		onDrop: function(targetRow, sourceRow, point){},	// point:'append','top','bottom'
	});
	$.extend($.fn.datagrid.methods, {
		_appendRows: function(jq, row){
			return jq.each(function(){
				var dg = $(this);
				var rows = $.isArray(row) ? row : [row];
				$.map(rows, function(row){
					dg.datagrid('appendRow', row).datagrid('enableDnd', dg.datagrid('getRows').length-1);
				});
			});
		},
		_insertRows: function(jq, param){
			return jq.each(function(){
				var dg = $(this);
				var index = param.index;
				var row = param.row;
				var rows = $.isArray(row) ? row : [row];
				$.map(rows, function(row, i){
					dg.datagrid('insertRow', {
						index: (index+i),
						row: row
					}).datagrid('enableDnd', index+i);
				});
			});
		},
		_getRowIndexs: function(jq, row){
			var dg = jq;
			var rows = $.isArray(row) ? row : [row];
			var indexs = $.map(rows, function(row){
				return dg.datagrid('getRowIndex', row);
			});
			return $.grep(indexs, function(index){
				if (index >= 0){return true;}
			});
		},
		_deleteRows: function(jq, indexs){
			return jq.each(function(){
				// sort desc
				indexs.sort(function(x,y){
					if (parseInt(x)>parseInt(y)){
						return -1;
					} else {
						return 1;
					}
				});
				for(var i=0; i<indexs.length; i++){
					$(this).datagrid('deleteRow', indexs[i]);
				}
			});
		},
		_setSelections: function(jq){
			return jq.each(function(){
				var rows = $(this).datagrid('getRows');
				for(var i=0; i<rows.length; i++){
					if (rows[i]._selected){
						$(this).datagrid('selectRow', i);
						rows[i]._selected = undefined;
					}
				}
			});
		},
		clearInsertingFlag: function(jq){
			return jq.each(function(){
				var opts = $(this).datagrid('options');
				if (opts.insertingIndex >= 0){
					var tr = opts.finder.getTr(this, opts.insertingIndex);
					tr.removeClass('datagrid-row-top datagrid-row-bottom');
					opts.insertingIndex = -1;
				}
			});
		}
	});

	var disabledDroppingRows = [];

	function enableDroppable(aa){
		$.map(aa, function(row){
			$(row).droppable('enable');
		});
	}

	$.extend($.fn.datagrid.methods, {
		resetDroppable: function(jq){
			return jq.each(function(){
				var c = $(this).datagrid('getPanel')[0];
				var my = [];
				var left = [];
				for(var i=0; i<disabledDroppingRows.length; i++){
					var t = disabledDroppingRows[i];
					var p = $(t).closest('div.datagrid-wrap');
					if (p.length && p[0] == c){
						my.push(t);
					} else {
						left.push(t);
					}
				}
				disabledDroppingRows = left;
				enableDroppable(my);
			});
		},
		enableDnd: function(jq, index){
			if (!$('#datagrid-dnd-style').length){
				$('<style id="datagrid-dnd-style">' +
					'.datagrid-row-top>td{border-top:1px solid red}' +
					'.datagrid-row-bottom>td{border-bottom:1px solid red}' +
					'</style>'
				).appendTo('head');
			}
			return jq.each(function(){
				var target = this;
				var state = $.data(this, 'datagrid');
				var dg = $(this);
				var opts = state.options;

				var draggableOptions = {
					disabled: false,
					revert: true,
					cursor: 'pointer',
					proxy: function(source) {
						var p = $('<div style="z-index:9999999999999"></div>').appendTo('body');
						var draggingRow = getDraggingRow(source);
						var rows = $.isArray(draggingRow) ? draggingRow : [draggingRow];
						$.map(rows, function(row,i){
							var index = dg.datagrid('getRowIndex', row);
							var tr1 = opts.finder.getTr(target, index, 'body', 1);
							var tr2 = opts.finder.getTr(target, index, 'body', 2);
							tr2.clone().removeAttr('id').removeClass('droppable').appendTo(p);
							tr1.clone().removeAttr('id').removeClass('droppable').find('td').insertBefore(p.find('tr:eq('+i+') td:first'));
							$('<td><span class="tree-dnd-icon tree-dnd-no" style="position:static">&nbsp;</span></td>').insertBefore(p.find('tr:eq('+i+') td:first'));
						});
						p.find('td').css('vertical-align','middle');
						p.hide();
						return p;
					},
					deltaX: 15,
					deltaY: 15,
					delay: opts.dragDelay,
					onBeforeDrag:function(e){
						var draggingRow = getDraggingRow(this);
						if (opts.onBeforeDrag.call(target, draggingRow) == false){return false;}
						if ($(e.target).parent().hasClass('datagrid-cell-check')){return false;}
						if (e.which != 1){return false;}
					},
					onStartDrag: function() {
						$(this).draggable('proxy').css({
							left: -10000,
							top: -10000
						});
						var draggingRow = getDraggingRow(this);
						setValid(draggingRow, false);
						state.draggingRow = draggingRow;
						opts.onStartDrag.call(target, draggingRow);
					},
					onDrag: function(e) {
						var x1=e.pageX,y1=e.pageY,x2=e.data.startX,y2=e.data.startY;
						var d = Math.sqrt((x1-x2)*(x1-x2)+(y1-y2)*(y1-y2));
						if (d>3){	// when drag a little distance, show the proxy object
							$(this).draggable('proxy').show();
							var tr = opts.finder.getTr(target, parseInt($(this).attr('datagrid-row-index')), 'body');
							$.extend(e.data, {
								startX: tr.offset().left,
								startY: tr.offset().top,
								offsetWidth: 0,
								offsetHeight: 0
							});
						}
						this.pageY = e.pageY;
					},
					onEndDrag: function(e){
						var dd = $(this).data('draggable').droppables.filter(function(){
							var dropObj = $(this);
							if (dropObj.droppable('options').disabled){return false;}
							if (dropObj.hasClass('datagrid-row') && !dropObj.hasClass('datagrid-row-over')){
								return false;
							}
							var p2 = dropObj.offset();
							if (e.pageX > p2.left && e.pageX < p2.left + dropObj.outerWidth()
									&& e.pageY > p2.top && e.pageY < p2.top + dropObj.outerHeight()){
								return true;
							} else {
								return false;
							}
						});
						var trs = dd.filter(function(){
							return $(this).hasClass('datagrid-row');
						});
						if (trs.length){
							dd = trs;
						}
						$(this).data('draggable').droppables = dd;
					},
					onStopDrag:function(){
						enableDroppable(disabledDroppingRows);
						disabledDroppingRows = [];
						setValid(state.draggingRow, true);
						opts.onStopDrag.call(target, state.draggingRow);
					}
				};
				var droppableOptions = {
					disabled: false,
					accept: opts.dropAccept,
					onDragEnter: function(e, source){
						if ($(this).droppable('options').disabled){return;}
						var dTarget = getDataGridTarget(this);
						var dOpts = $(dTarget).datagrid('options');
						var tr = dOpts.finder.getTr(dTarget, null, 'highlight');
						var sRow = getDraggingRow(source);
						var dRow = getRow(this);
						if (tr.length && dRow){
							cb();
						}

						function cb(){
							if (opts.onDragEnter.call(target, dRow, sRow) == false){
								$(dTarget).datagrid('clearInsertingFlag');
								tr.droppable('disable');
								tr.each(function(){
									disabledDroppingRows.push(this);
								});
							}
						}
					},
					onDragOver: function(e, source) {
						if ($(this).droppable('options').disabled){
							return;
						}
						if ($.inArray(this, disabledDroppingRows) >= 0){
							return;
						}
						var dTarget = getDataGridTarget(this);
						var dOpts = $(dTarget).datagrid('options');
						var tr = dOpts.finder.getTr(dTarget, null, 'highlight');
						if (tr.length){
							if (!isValid(tr)){
								setProxyFlag(source, false);
								return;
							}
						}
						setProxyFlag(source, true);

						var sRow = getDraggingRow(source);
						var dRow = getRow(this);
						if (tr.length){
							var pageY = source.pageY;
							var top = tr.offset().top;
							var bottom = tr.offset().top + tr.outerHeight();
							$(dTarget).datagrid('clearInsertingFlag');
							dOpts.insertingIndex = tr.attr('datagrid-row-index');
							if (pageY > top + (bottom - top) / 2) {
								tr.addClass('datagrid-row-bottom');
							} else {
								tr.addClass('datagrid-row-top');
							}
							if (dRow){
								cb();
							}
						}

						function cb(){
							if (opts.onDragOver.call(target, dRow, sRow) == false){
								setProxyFlag(source, false);
								$(dTarget).datagrid('clearInsertingFlag');
								tr.droppable('disable');
								tr.each(function(){
									disabledDroppingRows.push(this);
								});
							}
						}
					},
					onDragLeave: function(e, source) {
						if ($(this).droppable('options').disabled){
							return;
						}
						setProxyFlag(source, false);
						var dTarget = getDataGridTarget(this);
						$(dTarget).datagrid('clearInsertingFlag');
						var sRow = getDraggingRow(source);
						var dRow = getRow(this);
						if (dRow){
							opts.onDragLeave.call(target, dRow, sRow);
						}
					},
					onDrop: function(e, source) {
						if ($(this).droppable('options').disabled){
							return;
						}
						var sTarget = getDataGridTarget(source);
						var dTarget = getDataGridTarget(this);
						var dOpts = $(dTarget).datagrid('options');
						var tr = dOpts.finder.getTr(dTarget, null, 'highlight');

						var point = null;
						var sRow = getDraggingRow(source);
						var dRow = null;
						if (tr.length){
							if (!isValid(tr)){
								return;
							}
							point = tr.hasClass('datagrid-row-top') ? 'top' : 'bottom';
							dRow = getRow(tr);
						}

						$(dTarget).datagrid('clearInsertingFlag');
						if (opts.onBeforeDrop.call(target, dRow, sRow, point) == false){
							return;
						}
						insert.call(this);
						opts.onDrop.call(target, dRow, sRow, point);

						function insert(){
							var destIndex = parseInt(tr.attr('datagrid-row-index'));

							if (!point){
								var indexs = $(sTarget).datagrid('_getRowIndexs', sRow);
								$(dTarget).datagrid('_appendRows', sRow);
								$(sTarget).datagrid('_deleteRows', indexs);
								$(dTarget).datagrid('_setSelections');
							} else if (dTarget != sTarget){
								var index = point == 'top' ? destIndex : (destIndex+1);
								if (index >= 0){
									var indexs = $(sTarget).datagrid('_getRowIndexs', sRow);
									$(dTarget).datagrid('_insertRows', {
										index: index,
										row: sRow
									});
									$(sTarget).datagrid('_deleteRows', indexs);
									$(dTarget).datagrid('_setSelections');
								}
							} else {
								var dg = $(dTarget);
								var index = point == 'top' ? destIndex : (destIndex+1);
								if (index >= 0){
									var indexs = dg.datagrid('_getRowIndexs', sRow);
									var destIndex = parseInt(tr.attr('datagrid-row-index'));
									var index = point == 'top' ? destIndex : (destIndex+1);
									if (index >= 0){
										dg.datagrid('_insertRows', {
											index: index,
											row: sRow
										});
										for(var i=0; i<indexs.length; i++){
											if (indexs[i] > index){
												indexs[i] += indexs.length;
											}
										}
										dg.datagrid('_deleteRows', indexs);
										dg.datagrid('_setSelections');
									}
								}
							}
						}
					}
				}

				if (index != undefined){
					var trs = opts.finder.getTr(this, index);
				} else {
					var trs = opts.finder.getTr(this, 0, 'allbody');
				}
				trs.draggable(draggableOptions);
				trs.droppable(droppableOptions);
				setDroppable(target);

				function setProxyFlag(source, allowed){
					var icon = $(source).draggable('proxy').find('span.tree-dnd-icon');
					icon.removeClass('tree-dnd-yes tree-dnd-no').addClass(allowed ? 'tree-dnd-yes' : 'tree-dnd-no');
				}
				function getRow(tr){
					if (!$(tr).hasClass('datagrid-row')){return null}
					var target = $(tr).closest('div.datagrid-view').children('table')[0];
					var opts = $(target).datagrid('options');
					return opts.finder.getRow(target, $(tr));
				}
				function getDraggingRow(tr){
					if (!$(tr).hasClass('datagrid-row')){return null}
					var target = getDataGridTarget(tr);
					var opts = $(target).datagrid('options');
					var rows = $(target).datagrid('getRows');
					for(var i=0; i<rows.length; i++){
						rows[i]._selected = undefined;
					}
					if (opts.dragSelection){
						if ($(tr).hasClass('datagrid-row-selected')){
							var rows = $(target).datagrid('getSelections');
							$.map(rows, function(row){
								row._selected = true;
							});
							return rows;
						}
					}
					var row = opts.finder.getRow(target, $(tr));
					row._selected = $(tr).hasClass('datagrid-row-selected');
					return row;
				}
				function setDroppable(target){
					getDroppableBody(target).droppable(droppableOptions).droppable('enable');
				}
				function getDataGridTarget(el){
					return $(el).closest('div.datagrid-view').children('table')[0];
				}
				function getDroppableBody(target){
					var dc = $(target).data('datagrid').dc;
					return dc.view;
				}
				function isValid(tr){
					var opts = $(tr).droppable('options');
					if (opts.disabled || opts.accept == 'no-accept'){
						return false;
					} else {
						return true;
					}
				}
				function setValid(rows, valid){
					var accept = valid ? opts.dropAccept : 'no-accept';
					$.map($.isArray(rows)?rows:[rows], function(row){
						var index = $(target).datagrid('getRowIndex', row);
						opts.finder.getTr(target, index).droppable({accept:accept});
					});
				}
			});
		},
		disableDnd: function(jq){
			return jq.each(function(){
				var target = this;
				var state = $.data(this, 'datagrid');
				var dg = $(this);
				var opts = state.options;
				var trs = opts.finder.getTr(this, 0, 'allbody');
				trs.draggable('disable');
				trs.droppable('disable');
			});
		}

	});
})(jqBiz);
