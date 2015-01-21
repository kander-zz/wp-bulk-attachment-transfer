// IE < 9 fix for passing arbitrary arguments into setTimeout and setInterval.
/*@cc_on
 // conditional IE < 9 only fix
 @if (@_jscript_version <= 6)
 (function(f){
 window.setTimeout =f(window.setTimeout);
 window.setInterval =f(window.setInterval);
 })(function(f){return function(c,t){var a=[].slice.call(arguments,2);return f(function(){c.apply(this,a)},t)}});
 @end
 @*/

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

    // If only one thread is selected, introduce the delay option.
    $('#threads').on('change', function () {
        if ($(this).val() == 1) {
            $('#delayWrapper').show();
        } else {
            $('#delayWrapper').hide();
            $('#delay').val(0);
        }
    }).trigger('change');

    ('#delay').on('change', function() {
        if($(this).val() > 10) {
            alert("You are setting a delay of over 10 seconds. That means this will take some time. Grab a coffee!");
        }
    });
    $('#upload').on('click', function () {

        var file = $('#file').get(0).files[0],
            reader = new FileReader(),
            divOutput = $('#bulk-att-xfer-output'),
            author1 = $("input[name='author']:checked").val(),
            author2 = $("select[name='user']").val(),
            progressBar = $("#bulk-att-xfer-progressbar"),
            progressLabel = $("#bulk-att-xfer-progresslabel"),
            threads = $('#threads').val(),
            delay = $('#delay').val();

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
                attachmentInfo._ajax_nonce = BulkAttXferConfig.nonce;
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

            progressLabel.text(BulkAttXfer.importing);

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
                var url = BulkAttXferConfig.urls.siteurl + ajaxurl;

                var params = {
                    ajaxurl: url,
                    attachment: this,
                    delay: delay
                };

                pool
                    .run(BulkAttXferConfig.urls.worker, params)
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
                        } catch (err) {
                            $('<div class="error">Unknown server response</div>').prependTo(divOutput);
                        }

                        if(!response.type == undefined) {
                            $('<div class="error">' + response + '</div>').prependTo(divOutput);
                        } else {
                            $('<div class="' + response.type + '">' + response.message + '</div>').prependTo(divOutput);
                        }

                    });
            });
        }
    });
});
