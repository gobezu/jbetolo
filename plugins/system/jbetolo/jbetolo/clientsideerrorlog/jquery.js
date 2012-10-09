function jbetoloLogError(message, file, line) {
        $.ajax({
                url: document.location.origin+'/index.php?option=com_jbetolo&task=logclientsideerror',
                type: 'POST',
                data: JSON.stringify({json:{context:navigator.userAgent, message:message, file:file, line:line}}),
                contentType: 'application/json; charset=utf-8'
        }); 
}

window.onerror = function(message, file, line) { jbetoloLogError(message, file, line); };