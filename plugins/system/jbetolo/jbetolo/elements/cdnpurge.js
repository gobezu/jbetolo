//$Copyright$

var jbetolocdnpurge = new Class({
        Implements: [Options],
        
        initialize: function(options) {
                this.setOptions(options);

                window.addEvent('domready', function(){
                        this.assignActions();
                }.bind(this));
        },

        assignActions: function() {
                document.id('cdnpurgeBtn').addEvent('click', function() { this.cdnpurge(); }.bind(this));
        },

        /** field/param element actions **/

        cdnpurge: function(){
                var request = this.options.base+'index.php?option=com_jbetolo&task=cdnpurge';
                request += 
                        '&keys='+$('cdnpurgeKeys').get('value') + 
                        '&purge='+$('cdnpurgePurge').get('value') + 
                        '&cdn='+$('cdnpurgeCDN').get('value')
                ;

                new Request({
                        onSuccess: function(response) {
                                alert(response);
                        }.bind(this),
                        url: request,
                        noCache: true
                }).send();
                
                return false;
        }
});