/**
* @version:	2.0.0.b10-99 - 2012 March 09 16:03:58 +0300
* @package:	jbetolo
* @subpackage:	jbetolo
* @copyright:	Copyright (C) 2010 - 2011 jproven.com. All rights reserved. 
* @license:	GNU General Public License Version 2, or later http://www.gnu.org/licenses/gpl.html
*/

var jbetolosmushit = new Class({
        initialize: function(options) {
                this.setOptions(options);

                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                $('smushItBtn').addEvent('click', function() { this.smushIt(); }.bind(this));
        },

        /** field/param element actions **/

        smushIt: function() {
                this.toggle();

                var request = this.options.base+'index.php?option=com_jbetolo&task=smushit&dir=';
                var dir = $(this.options.smushitDir).value;
                var replace = $(this.options.smushitDir+'_replace').checked ? 'replace' : '';
                var recursive = $(this.options.smushitDir+'_recursive').checked ? 'recursive' : '';
                var fix = $(this.options.smushitDir+'_fix').value;

                if (!dir) {
                        alert(this.options.PLG_JBETOLO_SMUSHIT_DIRECTORY);
                        return;
                }

                request += dir + '&replace='+replace + '&recursive=' + recursive + '&fix=' + fix;

                if (MooTools.version.substr(2, 1) != 1) {
                        new Request({'url':request, onSuccess:function(response) { alert(response); this.toggle(); }.bind(this)}).send();
                } else {
                        new Ajax(request, {onSuccess:function(response) { alert(response); this.toggle(); }.bind(this)}).request();
                }
        },

        toggle: function() {
                if ($('smushItBtn').disabled) {
                        $('smushItBtn').disabled = false;
                        $('smushitprogress').setStyle('visibility', 'hidden');
                } else {
                        $('smushItBtn').disabled = true;
                        $('smushitprogress').setStyle('visibility', 'visible');
                }
        }
});

jbetolosmushit.implement(new Options);