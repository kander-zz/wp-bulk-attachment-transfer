jQuery(document).ready(function ($) {

    $(document).tooltip();

    var divInit = $('#bulk-att-xfer-init');

    // Check if the FileReader API is available to us. If not, load an error message and abort.
    // @TODO KISS: include the error message in the form and show/hide the div?
    if (window.FileReader && window.FormData && window.Worker) {
        $('#bulk-att-xfer-outdated').hide();
    } else {
        return;
    }

    $(document).on('click', '#upload', function () {

        var file = $('#file').get(0).files[0],
            reader = new FileReader(),
            divOutput = $('#bulk-att-xfer-output'),
            author1 = $("input[name='author']:checked").val(),
            author2 = $("select[name='user']").val(),
            progressBar = $("#bulk-att-xfer-progressbar"),
            progressLabel = $("#bulk-att-xfer-progresslabel"),
            threads = $('#threads').val();

        // Clean out the Output div.
        divOutput.empty();

        if (!file) {
            alert(BulkAttXfer.emptyInput);
            return;
        }

        $(function () {
            progressBar.progressbar({
                value: false
            });
            progressLabel.text(BulkAttXfer.parsing);
        });

        reader.readAsText(file);

        reader.onload = function (e) {

            var file = e.target.result,
                parser = new DOMParser(),
                xml = parser.parseFromString(file, "text/xml"),
                query = 'wp\\:post_type:contains(attachment), post_type:contains(attachment)',
                queue = [];

            // Find all items that have an element which matches the "post type with value 'attachment'" query.
            $(xml).find('item').has(query).each(function () {

                $this = $(this);

                var attachmentInfo = {};
                attachmentInfo._ajax_nonce = aiSecurity.nonce;
                attachmentInfo.url = $this.find('wp\\:attachment_url, attachment_url').text();
                attachmentInfo.title = $this.find('title').text();
                attachmentInfo.link = $this.find('link').text();
                attachmentInfo.pubDate = $this.find('pubDate').text();
                attachmentInfo.creator = $this.find('dc\\:creator, creator').text();
                attachmentInfo.guid = $this.find('guid').text();
                attachmentInfo.post_id = $this.find('wp\\:post_id, post_id').text();
                attachmentInfo.post_date = $this.find('wp\\:post_date, post_date').text();
                attachmentInfo.post_date_gmt = $this.find('wp\\:post_date_gmt, post_date_gmt').text();
                attachmentInfo.comment_status = $this.find('wp\\:comment_status, comment_status').text();
                attachmentInfo.ping_status = $this.find('wp\\:ping_status, ping_status').text();
                attachmentInfo.post_name = $this.find('wp\\:post_name, post_name').text();
                attachmentInfo.status = $this.find('wp\\:status, status').text();
                attachmentInfo.post_parent = $this.find('wp\\:post_parent, post_parent').text();
                attachmentInfo.menu_order = $this.find('wp\\:menu_order, menu_order').text();
                attachmentInfo.post_type = 'attachment';
                attachmentInfo.post_password = $this.find('wp\\:post_password, post_password').text();
                attachmentInfo.is_sticky = $this.find('wp\\:is_sticky, is_sticky').text();
                attachmentInfo.author1 = author1;
                attachmentInfo.author2 = author2;

                queue.push(attachmentInfo);
            });

            var pbMax = queue.length;

            if (pbMax == 0) {
                progressBar.progressbar("value", pbMax);
                $('<div class="error">' + BulkAttXfer.noAttachments + '</div>').prependTo(divOutput);
                return;
            }

            // Init progressbar based on the amount of files we've just determined.
            progressBar.progressbar({
                value: 0,
                max: pbMax,
                complete: function () {
                    console.log('Finish: ', new Date());
                    progressLabel.text(BulkAttXfer.done);
                }
            });

            // How many simultaneous workers in the pool?
            var pool = new ThreadPool(threads);


            $(queue).each(function () {
                var url = aiSecurity.urls.siteurl + ajaxurl;

                var params = {
                    ajaxurl: url,
                    attachment: this
                };

                pool
                    .run(import_attachment, params)
                    .done(function (result) {

                        console.log({
                            result: result,
                            threadpool: {
                                size: pool.size,
                                pending: pool.pendingJobs.length,
                                idle: pool.idleThreads.length,
                                active: pool.activeThreads.length
                            }
                        });

                        progressBar.progressbar("value", progressBar.progressbar("value") + 1);
                        try {
                            var response = JSON.parse(result.response);
                        } catch(err) {
                            $('<div class="error">Unknown server response</div>').prependTo(divOutput);
                        }

                        $('<div class="' + response.type + '">' + response.message + '</div>').prependTo(divOutput);

                    });
            });
        }
    });


    // This code runs as a worker.
    // Might want to move it into a separate script to reduce overhead introduced by ThreadPool, which
    // throws this into a data-url to turn it into a worker.
    function import_attachment(params, done) {

        // console.log(params);

        var url = params.ajaxurl;
        var data = new FormData();
        data.append('action', 'bat_upload');
        for (var i in params.attachment) {
            data.append(i, params.attachment[i])
        }

        // Unfortunately, we can not use jQuery in a Web Worker.
        // jQuery is built around the DOM, and loading it fails in a worker context.
        // Falling back to 'classic' XHR instead.
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.onload = function () {
            // do something to response
            done({
                response: this.responseText
            });
        };
        xhr.send(data);
    }
});
