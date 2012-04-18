/**
* @version:	2.0.0.b10-99 - 2012 March 09 16:03:58 +0300
* @package:	jbetolo
* @subpackage:	jbetolo
* @copyright:	Copyright (C) 2010 - 2011 jproven.com. All rights reserved. 
* @license:	GNU General Public License Version 2, or later http://www.gnu.org/licenses/gpl.html
*/

var jbetolosettings = new Class({
        initialize: function(options) {
                this.setOptions(options);

                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                $('saveSettingBtn').addEvent('click', function() { this.saveCurrent(); }.bind(this));
                $('readSettingBtn').addEvent('click', function() { this.readCurrent(); }.bind(this));
                $('pingBtn').addEvent('click', function() { this.ping(); }.bind(this));

                this.settingsSelector().addEvent('change', function(e) { this.resetSettings(e); }.bind(this));
        },

        /** field/param element actions **/

        resetSettings: function(e) {
                e = e.target ? e.target : e.srcElement;
                
                var cmsg = this.options.PLG_JBETOLO_PREDEFINED_CONFIRM;
                var o = e.options[e.selectedIndex];

                if (!o.value || o.value == -1) return;

                var c = confirm(cmsg+o.text);

                if (c == true) {
                        var request = this.options.base+'index.php?option=com_jbetolo&task=resetsetting&setting='+o.value;

                        var opts = {
                                onSuccess: function() {
                                        alert(this.options.PLG_JBETOLO_PREDEFINED_SUCCESS);
                                        document.location.reload();
                                }.bind(this)
                        };

                        if (MooTools.version.substr(2, 1) != 1) {
                                opts['url'] = request;
                                new Request(opts).send();
                        } else {
                                new Ajax(request, opts).request();
                        }
                }
        },

        saveCurrent: function() {
                var settingName = prompt(this.options.PLG_JBETOLO_PREDEFINED_SAVENAME);

                while (settingName) {
                        if (!settingName.test(/[a-z0-9]/i) || settingName.length > 32) {
                                settingName = prompt(this.options.PLG_JBETOLO_PREDEFINED_SAVENAME);
                        } else if (this.isSelectMember(this.settingsSelector(), settingName)) {
                                settingName = prompt(this.options.PLG_JBETOLO_PREDEFINED_NAMEEXISTS);
                        } else {
                                break;
                        }
                }

                if (!settingName) return;

                var author = prompt(this.options.PLG_JBETOLO_PREDEFINED_SAVEAUTHOR);

                var form = new Element('form', {action:'index.php?option=com_jbetolo&task=savesetting&name='+settingName});
                var settings = this.getSettings();
                
                settings = settings.replace(/load_predefined_settings=.*/, 'load_predefined_settings=-1');

                if (author) {
                        settings = ';Author: ' + author.trim() + "\r\n" + settings;
                }

                new Element('input', {type:'text', name:'settings', value:settings}).inject(form);

                var opts = {
                        onFailure: function(){
                                alert(this.options.PLG_JBETOLO_PREDEFINED_SAVEFAILURE);
                        }.bind(this),
                        onSuccess: function(response) {
                                if (response == '1') {
                                        this.addOptionToSelect(settingName, settingName, this.settingsSelector(), true);
                                        alert(this.options.PLG_JBETOLO_PREDEFINED_SAVESUCCESS + settingName);
                                } else {
                                        alert(this.options.PLG_JBETOLO_PREDEFINED_SAVEFAILURE);
                                }
                        }.bind(this)
                };

                if (this.options.j16) {
                        form.set('send', opts);
                        form.send();
                } else {
                        form.send(opts);
                }

                return;
        },

        readCurrent: function() {
                var el = new Element('div');
                
                el.innerHTML = this.getSettings('<br />');

                SqueezeBox.fromElement(el, {
                        handler: 'adopt',
                        size: {x: 300, y: 500}
                });
        },
        
        ping: function() {
                var options = {
                        method: 'get',
                        noCache: true,
                        onSuccess: function () {
                                alert('Success');
                        }.bind(this),
                        onFailure: function() {
                                alert('Failed');
                        }
                };
                
                var url = this.options.base+'index.php?option=com_jbetolo&task=ping';
                alert(url);
                
                if (MooTools.version.substr(2, 1) != 1) {
                        options['url'] = url;
                        new Request(options).request();
                } else {
                        new Ajax(url, options).request();
                }
        },

        getSettings: function(sep) {
                var form = this.form();
                var vals = this.getFormValue(form, 'params', true, '|', true, 'string', sep, true);
                
                vals = vals
                        .replace(/params\[([^\]]+)\]/g, '$1')
                        .replace(/jform\[params\]\[([^\]]+)\]/g, '$1');

                return vals;
        },

        settingsSelectorID: function() {
                return this.options.prefix + this.options.settingsSelectorID;
        },

        settingsSelector: function() {
                return $(this.settingsSelectorID());
        },

        form: function() {
                return this.settingsSelector().form;
        },

        /*** Basic form handling ***/
        
        removeFormElementValue: function(el, val) {
                if (!el) return;

                el = $(el);

                var type = el.tagName.toLowerCase();

                if (type == 'select') {
                        for (var i = 0, n = el.options.length; i < n; i++) {
                                if (el.options[i].value == val) {
                                        if (el.options[i].selected) {
                                                el.selectedIndex = -1;
                                        }

                                        el.options[i] = null;

                                        break;
                                }
                        }
                } else if (type == 'input' && el.getProperty('type') == 'radio') {
                        el.form.getElements('input[name='+el.getProperty('name')+']').each (function(el) {
                                if (el.getProperty('value') == val) {
                                        el.dispose();
                                }
                        });
                } else {
                        el.setProperty('value', '');
                }
        },

        resetFormElementValue: function(el) {
                if (!el) return;

                el = $(el);

                var type = el.tagName.toLowerCase();

                if (type == 'select') {
                        for (var i = 0, n = el.options.length; i < n; i++) {
                                el.options[i].selected = el.options[i].value == '';
                        }
                } else if (type == 'input' && el.getProperty('type') == 'radio') {
                        el.form.getElements('input[name='+el.getProperty('name')+']').each (function(el) {
                                el.checked = false;
                        });
                } else {
                        el.setProperty('value', '');
                }
        },

        selected: function(sel, type) {
                if (!$(sel)) return;
                return $(sel).options[$(sel).selectedIndex][type ? type : 'value'];
        },

        setFormElementValue: function(el, val, src) {
                if (!el) return;

                el = $(src ? src  + el : $(el).getProperty('id'));

                var type = el.tagName.toLowerCase();

                if (type == 'select') {
                        for (var i = 0, n = el.options.length; i < n; i++) {
                                if (el.options[i].value == val) {
                                        el.options[i].selected = true;
                                        break;
                                }
                        }
                } else if (type == 'input' && el.getProperty('type') == 'radio') {
                        el.form.getElements('input[name='+el.getProperty('name')+']').each (function(el) {
                                if (el.getProperty('value') == val) {
                                        el.checked = true;
                                }
                        });
                } else {
                        el.setProperty('value', val);
                }
        },

        getFormElementValue: function(el) {
                if (!el) return;

                el = $(el);

                var type = el.tagName.toLowerCase(), val;

                if (type == 'select') {
                        if (el.multiple) {
                                val = [];

                                for (var i = 0; i < el.options.length; i++) {
                                        if (el.options[i].selected) {
                                                val.push(el.options[i].value);
                                        }
                                }

                                if (val.length == 0) val = undefined;
                        } else {
                                val = this.selected(el);
                        }
                } else if (type == 'input' && el.getProperty('type') == 'radio') {
                        $(el.form).getElements('input[type=radio]').each(function(e) {
                                if (e.getProperty('name') == el.getProperty('name') && e.checked) {
                                        val = e.value;
                                }
                        });
                } else {
                        val = el.getProperty('value').trim();

                        if (el.getProperty('alt') && el.getProperty('alt') == val && el.hasClass('hint')) {
                                val = '';
                        }
                }

                return val;
        },
        
        getFormValue: function(form, selectors, includeEmpty, separator, flatten, resultAs, resultSep, quote) {
                if (resultAs == undefined) {
                        resultAs = 'string';
                }

                if (includeEmpty == undefined) {
                        includeEmpty = false;
                }

                if (separator == undefined) {
                        separator = '|';
                }

                if (flatten == undefined) {
                        flatten = false;
                }

                if (resultSep == undefined) {
                        resultSep = "\r\n";
                }

                var sels = 'input,textarea,select';

                if (selectors == 'params') {
                        if (this.options.prefix == 'params') {
                                sels = '[name^=params\[]';
                        } else {

                                sels = '[name^=jform\[params]';
                        }
                        
                } else if (selectors != undefined) {
                        sels = selectors;
                }

                form = $(form);

                var values = {}, value, name;

                form.getElements(sels).each(function(el) {
                        name = el.name;

                        if (!name) return;

                        if (el.tagName.toLowerCase() == 'input' && el.getProperty('type') == 'radio') {
                                if (values.hasOwnProperty(name)) return;
                        }

                        value = this.getFormElementValue(el);

                        if (value || includeEmpty) {
                                if (value) {
                                        if ($type(value) == 'array') {
                                                value = value.join(separator);
                                        }

                                        value = value.trim();
                                }

                                if (value && values.hasOwnProperty(name)) {
                                        if (values[name]) {
                                                values[name] += separator;
                                        }

                                        values[name] += value;
                                } else {
                                        values[name] = value || '';
                                }
                        }
                }.bind(this));

                if (selectors == 'params') {
                        var vals = '', val;

                        for (name in values) {
                                if (vals) vals += resultSep;
                                
                                val = values[name];

                                if (val && quote && $type(val) != 'number' && !/[0-9]+/.test(val)) {
                                        val = '"' + val + '"';
                                }
                                
                                vals += name + '=' + val;
                        }

                        values = vals;
                } else if (resultAs == 'string') {
                        values = Json.toString(values);
                }

                return values;
        },

        createSelect: function(values, valName, txtName, id, name, firstElementTxt) {
                var el = new Element('select'), val, txt;

                if (id) el.setProperty('id', id);

                if (name) {
                        el.setProperty('name', name);
                } else if (id) {
                        el.setProperty('name', id);
                }

                if (!valName) valName = 'value';
                if (!txtName) txtName = 'text';

                if (firstElementTxt) {
                        this.addOptionToSelect('', firstElementTxt, el);
                }

                for (var i = 0; i < values.length; i++) {
                        if ($type(values[i]) == 'array' || $type(values[i]) == 'object') {
                                val = values[i][valName];
                                txt = values[i][txtName];
                        } else {
                                val = txt = values[i];
                        }

                        this.addOptionToSelect(val, txt, el);
                }

                return el;
        },

        addOptionToSelect: function(val, txt, sel, activate) {
                if (!txt) txt = val;

                sel = $(sel)

                for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].value == val) {
                                sel.options[i].text = txt;
                                return;
                        }
                }

                var opt = new Option(txt, val);

                $(opt).injectInside(sel);
                $(opt).innerHTML = txt;

                if (activate) {
                        this.setFormElementValue(sel, val);
                }
        },

        isSelectMember: function(sel, val) {
                sel = $(sel)

                for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].value == val) {
                                return true;
                        }
                }

                return false;
        }
});

jbetolosettings.implement(new Options);