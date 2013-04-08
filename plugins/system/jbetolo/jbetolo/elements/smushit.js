//$Copyright$

var jbetolosmushit = new Class({
        Implements: [Options],
        
        initialize: function(options) {
                this.setOptions(options);
                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                document.id('smushItBtn').addEvent('click', function() { this.smushIt(); }.bind(this));
        },

        /** field/param element actions **/

        smushIt: function() {
                this.toggle();

                var request = this.options.base+'index.php?option=com_jbetolo&task=smushit&dir=';
                var dir = document.id(this.options.smushitDir).value;
                var replace = document.id(this.options.smushitDir+'_replace').checked ? 'replace' : '';
                var recursive = document.id(this.options.smushitDir+'_recursive').checked ? 'recursive' : '';
                var fix = document.id(this.options.smushitDir+'_fix').value;

                if (!dir) {
                        alert(this.options.PLG_JBETOLO_SMUSHIT_DIRECTORY);
                        return;
                }

                request += dir + '&replace='+replace + '&recursive=' + recursive + '&fix=' + fix;

                new Request({
                        url:request, 
                        noCache:true, 
                        onSuccess:function(response) { 
                                alert(response); 
                                this.toggle(); 
                        }.bind(this)
                }).send();
        },

        toggle: function() {
                if (document.id('smushItBtn').disabled) {
                        document.id('smushItBtn').disabled = false;
                        document.id('smushitprogress').setStyle('visibility', 'hidden');
                } else {
                        document.id('smushItBtn').disabled = true;
                        document.id('smushitprogress').setStyle('visibility', 'visible');
                }
        }
});