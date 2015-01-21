onmessage = function (event) {
    var data = event.data;
    import_attachment(data.ajaxurl, data.delay, data.attachment, function (result) {
        postMessage(result);
    });
};


// This code runs as a worker.
// Might want to move it into a separate script to reduce overhead introduced by ThreadPool, which
// throws this into a data-url to turn it into a worker.
function import_attachment(url, delay, attachment, done) {

    console.log(url, delay, attachment);

    var data = new FormData();
    data.append('action', 'bat_upload');

    for (var i in attachment) {
        data.append(i, attachment[i])
    }

    // Set a timeout; if a delay is set, this gives us some extra breathing room.
    setTimeout(function (url, data) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.onload = function () {
            // do something to response
            done({
                response: this.responseText
            });
        };
        xhr.send(data);
    }, delay * 1000, url, data);

}
