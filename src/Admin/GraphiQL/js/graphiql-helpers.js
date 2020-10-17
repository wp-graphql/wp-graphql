$j=jQuery.noConflict();

$j(document).ready(function(){

    $j('.update-nag').hide();
    $j('.error').hide();

    $defaultHeight = '500px';
    $wpWrapHeight = $j('#wpwrap').height();
    $adminBarHeight = $j('#wpadminbar').height();
    $footerHeight = $j('#wpfooter').height();
    $height = ( $wpWrapHeight - $adminBarHeight - $footerHeight - 65 );
    $graphiqlHeight = ( $defaultHeight < $height ) ? $defaultHeight : $height;
    $j('#graphiql').css( 'height', $graphiqlHeight );
});
