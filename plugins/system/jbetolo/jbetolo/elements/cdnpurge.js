//$Copyright$

var jbetolocdnpurge = new Class({
        initialize: function(options) {
                this.setOptions(options);

                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                $('cdnpurgeBtn').addEvent('click', function() { this.cdnpurge(); }.bind(this));
        },

        /** field/param element actions **/

        cdnpurge: function(){
                var request = this.options.base+'index.php?option=com_jbetolo&task=cdnpurge';
                request += 
                        '&keys='+$('cdnpurgeKeys').get('value') + 
                        '&purge='+$('cdnpurgePurge').get('value') + 
                        '&cdn='+$('cdnpurgeCDN').get('value')
                ;

                var opts = {
                        onSuccess: function(response) {
                                alert(response);
                        }.bind(this)
                };

                if (MooTools.version.substr(2, 1) != 1) {
                        opts['url'] = request;
                        new Request(opts).send();
                } else {
                        new Ajax(request, opts).request();
                }
                
                return false;
        }
});

jbetolocdnpurge.implement(new Options);