function jbetoloLogError(message, file, line) {
        new Request.JSON({
                url: document.location.href+'/index.php?option=com_jbetolo&task=logclientsideerror',
                method: 'post',
                data: {json:{context:navigator.userAgent, message:message, file:file, line:line}}
        }).send();
}

window.onerror = function(message, file, line) { jbetoloLogError(message, file, line); };