//$Copyright$

var jbetolohtaccess = new Class({
        Implements: [Options],
        
        initialize: function(options) {
                this.setOptions(options);

                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                document.id('htaccessBtn').addEvent('click', function() { this.htaccess('site'); }.bind(this));
        },

        /** field/param element actions **/

        htaccess: function(){
                new Request({
                        onSuccess: function(response) {
                                alert(response);
                        }.bind(this),
                        url: this.options.base+'index.php?option=com_jbetolo&task=htaccess',
                        noCache: true
                }).send();
                
                return false;
        }
});