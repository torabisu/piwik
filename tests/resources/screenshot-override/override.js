(function ($) {

    var DEBUG_LOGGING = true;
    
    if (DEBUG_LOGGING) {
        var log = function(message) {
            console.log(message);
        };
    } else {
        var log = function() {};
    }

    var triggerRenderInsane = function () {
        console.log("__AJAX_DONE__");
    };
    
    var triggerRender = function () {
        if (window.globalAjaxQueue.active === 0) { // sanity check
            triggerRenderInsane();
        }
    };

    var waitABunch = function (individualWaitTime, waitCount, callback, current) {
        current = typeof current == 'undefined' ? 0 : current;

        if (current < waitCount) {
            setTimeout(function () {
                waitABunch(individualWaitTime, waitCount, callback, current + 1);
            }, individualWaitTime);
        } else {
            callback();
        }
    }

    var triggerRenderIfNoAjax = function () {
        setTimeout(function () { // allow other javascript to execute in case they execute ajax/add images/set the src of images
            if (window.globalAjaxQueue.active === 0) {
                $('body').waitForImages({
                    waitForAll: true,
                    finished: function () {
                        // wait some more to make sure other javascript is executed & the last image is rendered
                        waitABunch(100, 10, triggerRender);
                    },
                });
            }
        }, 1);
    };

    window.piwik = window.piwik || {};
    window.piwik.ajaxRequestFinished = triggerRenderIfNoAjax;
    window.piwik._triggerRenderInsane = triggerRenderInsane;

    window.piwik.jqplotLabelFont = 'Open Sans';

    // in case there are no ajax requests, try triggering after a sec
    setTimeout(function () {
        window.piwik.ajaxRequestFinished();
    }, 1000);

}(jQuery));