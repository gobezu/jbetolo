//$Copyright$

var jbetolohtaccess = new Class({
        initialize: function(options) {
                this.setOptions(options);

                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                $('htaccessBtn').addEvent('click', function() { this.htaccess('site'); }.bind(this));
        },

        /** field/param element actions **/

        htaccess: function(){
                var request = this.options.base+'index.php?option=com_jbetolo&task=htaccess';

                var opts = {
                        onSuccess: function(response) {
                                alert(response);
                        }.bind(this)
                };

                if (MooTools.version.substr(2, 1) != 1) {
                        opts['url'] = request;
                        opts['noCache'] = true;
                        new Request(opts).send();
                } else {
                        new Ajax(request, opts).request();
                }
                
                return false;
        }
});

jbetolohtaccess.implement(new Options);