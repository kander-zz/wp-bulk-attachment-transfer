<div class="wrap">
    <h2><?php _e( 'Bulk Attachment Transfer', 'bulk-att-xfer' ); ?></h2>

    <noscript>
        <div class="error">
            <p><?php _e( 'Your browser doesn\'t have JavaScript enabled; this plugin requires JavaScript to function.',
                    'bulk-att-xfer' ); ?></p>

            <p><?php _e( 'Please enable JavaScript for this site to continue.', 'bulk-att-xfer' ); ?></p>
        </div>
    </noscript>
    <div class="error" id="bulk-att-xfer-outdated">
        <p><?php _e( 'Sorry, but you\'re using an <strong>outdated</strong> browser that doesn\'t support the features required to use this plugin.', 'bulk-att-xfer' ); ?></p>
        <p><?php echo sprintf( __( 'You must <a href="%s">upgrade your browser</a> in order to use this plugin.', 'bulk-att-xfer' ), 'http://browsehappy.com' ); ?></p>
    </div>

    <div id="bulk-att-xfer-init">
        <p><?php _e('Select the WordPress eXtended RSS (WXR) file. This plugin will try to transfer any images in the WXR file to this blog.', 'bulk-att-xfer' ); ?></p>

        <p><?php _e( 'Choose a WXR (.xml) file from your computer and press upload.', 'bulk-att-xfer' ); ?></p>

        <p><input type="file" name="file" id="file" required="required" /></p>

        <p>
            <?php _e( 'Attribute uploaded images to:', 'bulk-att-xfer' ); ?><br/>
            <input type="radio" name="author" value=1 checked/>&nbsp;<?php _e( 'Current User',
                'bulk-att-xfer' ); ?><br/>
            <input type="radio" name="author" value=2/>&nbsp;<?php _e( 'User in the import file',
                'bulk-att-xfer' ); ?>
            <br/>
            <input type="radio" name="author" value=3/>&nbsp;<?php _e( 'Select User:', 'bulk-att-xfer' ); ?>
            <?php wp_dropdown_users(); ?>
        </p>

        <p>
            <!-- max of 16 threads due to limits imposed by browsers. Above 8 no real performance gain was observed. -->
            <input type="number" name="threads" id="threads" value="5" min="1" max="16">&nbsp;
            <?php _e( 'Number of simultaneous uploads; if you set this to 1 you can also specify a delay between each transfer', 'bulk-att-xfer' ); ?>
        </p>

        <p id="delayWrapper">
            <input type="number" name="delay" id="delay" value="0" min="0" max="60">&nbsp;
            <?php _e( 'Delay between uploads in seconds', 'bulk-att-xfer' ); ?>
        </p>

        <p><?php submit_button( _x( 'Upload', 'A button which will submit the attachment for processing when clicked.',
                'bulk-att-xfer' ), 'secondary', 'upload', false ); ?></p>

    </div>
    <div id="bulk-att-xfer-progressbar">
        <div id="bulk-att-xfer-progresslabel"></div>
    </div>
    <div id="bulk-att-xfer-output"></div>

</div>