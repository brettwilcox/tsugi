<?php
require_once "../../config.php";
require_once $CFG->dirroot."/admin/admin_util.php";

$local_path = route_get_local_path(__DIR__);
if ( $local_path == "canvas-config.xml" ) {
    require_once("canvas-config-xml.php");
    return;
}
if ( $local_path == "casa.json" ) {
    require_once("casa-json.php");
    return;
}

use \Tsugi\Core\Settings;
use \Tsugi\Core\LTIX;
use \Tsugi\Util\LTI;
use \Tsugi\Util\ContentItem;
use \Tsugi\UI\Lessons;

// No parameter means we require CONTEXT, USER, and LINK
$LAUNCH = LTIX::requireData(LTIX::USER);

// Model
$p = $CFG->dbprefix;

$lti_post = LTIX::postArray();

$return_url = ContentItem::returnUrl($lti_post);
$allow_lti = ContentItem::allowLtiLinkItem($lti_post);
$allow_web = ContentItem::allowContentItem($lti_post);

$OUTPUT->header();
?>
<style>
    .card {
        border: 1px solid black;
        margin: 5px;
        padding: 5px;
    }
#loader {
      position: fixed;
      left: 0px;
      top: 0px;
      width: 100%;
      height: 100%;
      background-color: white;
      margin: 0;
      z-index: 100;
}
#XbasicltiDebugToggle {
    display: none;
}
</style>
<?php

// Load Lessons Data
$l = false;
$assignments = false;
if ( ($allow_lti || $allow_web) && isset($CFG->lessons) ) {
    $l = new Lessons($CFG->lessons);
    if ( $allow_web ) $contents = true;
    foreach($l->lessons->modules as $module) {
        if ( isset($module->lti) ) {
            if ( $allow_lti ) $assignments = true;
        }
    }
}

// Load Tool Registrations
if ( $allow_lti ) {
    $registrations = findAllRegistrations();
    if ( count($registrations) < 1 ) $registrations = false;
} else {
    $registrations = false;
}

$OUTPUT->bodyStart();
$OUTPUT->flashMessages();

if ( ! $USER->instructor ) {
    echo("<p>This tool must be launched by the instructor</p>");
    $OUTPUT->footer();
    exit();
}

if ( ! $return_url ) {
    echo("<p>This tool must be with LTI Link Content Item support</p>");
    $OUTPUT->footer();
    exit();
}

if ( ! ( $assignments || $contents || $registrations ) ) {
    echo("<p>No tools, content, or assignments found.</p>");
    $OUTPUT->footer();
    exit();
}

// Handle the tool install
if ( isset($_GET['install']) ) {
    $install = $_GET['install'];
    if ( ! isset($registrations[$install])) {
        echo("<p>Tool registration for ".htmlentities($install)." not found</p>\n");
        $OUTPUT->footer();
        exit();
    }
    $tool = $registrations[$install];

    $title = $tool['name'];
    $text = $tool['description'];
    $fa_icon = isset($tool['FontAwesome']) ? $tool['FontAwesome'] : false;
    $icon = false;
    if ( $fa_icon !== false ) {
        $icon = $CFG->staticroot.'/font-awesome-4.4.0/png/'.str_replace('fa-','',$fa_icon).'.png';
    }

    if ( $fa_icon ) {
        echo('<i class="fa '.$fa_icon.' fa-3x" style="color: #1894C7; float:right; margin: 2px"></i>');
    }
    echo('<center>');
    echo("<h1>".htmlent_utf8($title)."</h1>\n");
    echo("<p>".htmlent_utf8($text)."</p>\n");
    $script = isset($tool['script']) ? $tool['script'] : "index.php";
    $path = $tool['url'];

    // Title is for the href and text is for display
    $json = LTI::getLtiLinkJSON($path, $title, $title, $icon, $fa_icon);
    $retval = json_encode($json);

    $parms = array();
    $parms["lti_message_type"] = "ContentItemSelection";
    $parms["lti_version"] = "LTI-1p0";
    $parms["content_items"] = $retval;
    $data = LTIX::postGet('data');
    if ( $data ) $parms['data'] = $data;

    $parms = LTIX::signParameters($parms, $return_url, "POST", "Install Tool");
    $endform = '<a href="index.php" class="btn btn-warning">Back to Store</a>';
    $content = LTI::postLaunchHTML($parms, $return_url, true, false, $endform);
    echo($content);
    $OUTPUT->footer();
    exit();
} 

// Handle the assignment install
if ( $l && isset($_GET['assignment']) ) {
    $lti = $l->getLtiByRlid($_GET['assignment']);
    if ( ! $lti ) {
        echo("<p>Assignment ".htmlentities($_GET['assignment'])." not found</p>\n");
        $OUTPUT->footer();
        exit();
    }

    $title = $lti->title;
    $path = $lti->launch;
    $path .= strpos($path,'?') === false ? '?' : '&';
    // Sigh - some LMSs don't handle custom - sigh
    $path .= 'inherit=' . urlencode($_GET['assignment']);
    $fa_icon = 'fa-check-square-o';
    $icon = $CFG->staticroot.'/font-awesome-4.4.0/png/'.str_replace('fa-','',$fa_icon).'.png';

    // Compute the custom values
    $custom = array();
    $custom['canvas_xapi_url'] = '$Canvas.xapi.url';
    if ( isset($lti->custom) ) {
        foreach($lti->custom as $entry) {
            if ( !isset($entry->key) ) continue;
            if ( isset($entry->json) ) {
                $value = json_encode($entry->json);
            } else if ( isset($entry->value) ) {
                $value = $entry->value;
            }
            $custom[$entry->key] = $value;
        }
    }

    // Title is for the href and text is for display
    $json = LTI::getLtiLinkJSON($path, $title, $title, $icon, $fa_icon, $custom);
    $retval = json_encode($json);

    $parms = array();
    $parms["lti_message_type"] = "ContentItemSelection";
    $parms["lti_version"] = "LTI-1p0";
    $parms["content_items"] = $retval;
    $data = LTIX::postGet('data');
    if ( $data ) $parms['data'] = $data;

    $parms = LTIX::signParameters($parms, $return_url, "POST", "Install Tool");
    $endform = '<a href="index.php" class="btn btn-warning">Back to Store</a>';
    $content = LTI::postLaunchHTML($parms, $return_url, true, false, $endform);
    echo('<center>');
    if ( $fa_icon ) {
        echo('<i class="fa '.$fa_icon.' fa-3x" style="color: #1894C7; float:right; margin: 2px"></i>');
    }
    echo("<h1>".htmlent_utf8($title)."</h1>\n");
    echo($content);
    $OUTPUT->footer();
    exit();
} 

// Handle the content install
$content_items = array();
foreach($_GET as $k => $v) {
    if ($v == 'on') $content_items[] = $k;
}
if ($l && count($content_items) > 0 ) {
    $retval = new ContentItem();
    $count = 0;
    foreach($content_items as $ci) {
        $pieces = explode('::', $ci);
        if ( count($pieces) != 2 ) continue;
        if ( ! is_numeric($pieces[1]) ) continue;
        $anchor = $pieces[0];
        $index = $pieces[1]+0;
        $module = $l->getModuleByAnchor($anchor);
        if ( ! $module ) continue;
        $resources = Lessons::getUrlResources($module);
        if ( ! $resources ) continue;
        if ( ! isset($resources[$index]) ) continue;
        $r = $resources[$index];
        $retval->addContentItem($r->url, $r->title, $r->title, $r->thumbnail, $r->icon);
        if ( $count == 0 ) {
            echo("<p>Selected items:</p>\n");
            echo("<ul>\n");
        }
        $count++;
        echo("<li>".htmlentities($r->title)."</li>\n");
    }

    if ( $count < 1 ) {
        echo("<p>No valid content items to install</p>\n");
        $OUTPUT->footer();
        exit();
    }
    echo("</ul>\n");
        
    $data = LTIX::postGet('data');
    $parms = $retval->getContentItemSelection($data);

    $parms = LTIX::signParameters($parms, $return_url, "POST", "Install Content");
    $endform = '<a href="index.php" class="btn btn-warning">Back to Store</a>';
    $content = LTI::postLaunchHTML($parms, $return_url, true, false, $endform);
    echo('<center>');
    echo($content);
    $OUTPUT->footer();
    exit();
}
    

$active = 'active';

echo('<ul class="nav nav-tabs">'."\n");
if ( $registrations && $allow_lti ) {
    echo('<li class="'.$active.'"><a href="#box" data-toggle="tab" aria-expanded="true">Tools</a></li>'."\n");
    $active = '';
}
if ( $l && $allow_web ) {
    echo('<li class="'.$active.'"><a href="#content" data-toggle="tab" aria-expanded="false">Content</a></li>'."\n");
    $active = '';
}
if ( $l && $allow_lti ) {
    echo('<li class="'.$active.'"><a href="#assignments" data-toggle="tab" aria-expanded="false">Assignments</a></li>'."\n");
    $active = '';
}
echo("</ul>\n");

$active = 'active';
echo('<div id="myTabContent" class="tab-content">'."\n");

// Render the tools in the site
if ( $registrations && $allow_lti ) {
    echo('<div class="tab-pane fade '.$active.' in" id="box">'."\n");
    $active = '';

    $count = 0;
    foreach($registrations as $name => $tool ) {

        $title = $tool['name'];
        $text = $tool['description'];
        $fa_icon = isset($tool['FontAwesome']) ? $tool['FontAwesome'] : false;
        $icon = false;
        if ( $fa_icon !== false ) {
            $icon = $CFG->staticroot.'/font-awesome-4.4.0/png/'.str_replace('fa-','',$fa_icon).'.png';
        }

        echo('<div style="border: 2px, solid, red;" class="card">');
        if ( $fa_icon ) {
            echo('<a href="index.php?install='.urlencode($name).'">');
            echo('<i class="fa '.$fa_icon.' fa-2x" style="color: #1894C7; float:right; margin: 2px"></i>');
            echo('</a>');
        }
        echo('<p><strong>'.htmlent_utf8($title)."</strong></p>");
        echo('<p>'.htmlent_utf8($text)."</p>\n");
        echo('<center><a href="index.php?install='.urlencode($name).'" class="btn btn-default" role="button">Details</a></center>');
        echo("</div>\n");

        $count++;
    }
    if ( $count < 1 ) {
        echo("<p>No available tools</p>\n");
    }
    echo("</div>\n");
}

if ( $l && $allow_web ) {
    echo('<div class="tab-pane fade '.$active.' in" id="content">'."\n");
    $active = '';

    echo("&nbsp;<br/>\n");
    echo("<form>\n");
    $count = 0;
    foreach($l->lessons->modules as $module) {
        $resources = Lessons::getUrlResources($module);
        if ( count($resources) < 1 ) continue;
        if ( $count == 0 ) {
            echo('<p><input type="submit" class="btn btn-default" value="Install"></p>'."\n");
            echo("<ul>\n");
        }
        $count++;
        echo('<li>'.$module->title."\n");
        echo('<ul style="list-style-type: none;">'."\n");
        for($i=0; $i<count($resources); $i++ ) {
            $resource = $resources[$i];
            echo('<li>');
            echo('<input type="checkbox" value="on", name="'.$module->anchor.'::'.$i.'">');
            echo('<a href="'.$resource->url.'" target="_blank">');
            echo(htmlentities($resource->title));
            echo("</a>\n");
            echo('</li>');
        }
        echo("</ul>\n");
        echo("</li>\n");
    }
    if ( $count < 1 ) {
        echo("<p>No available content.</p>\n");
    } else {
        echo("</ul>\n");
    }
    echo('<p><input type="submit" class="btn btn-default" value="Install"></p>'."\n");
    echo("</form>\n");
    echo("</div>\n");
}

// Render web assignments
if ( $l && $allow_lti ) {
    echo('<div class="tab-pane fade '.$active.' in" id="assignments">'."\n");
    $active = '';
    $count = 0;
    foreach($l->lessons->modules as $module) {
        if ( isset($module->lti) ) {
            foreach($module->lti as $lti) {
                if ( $count == 0 ) {
                    echo("<ul>\n");
                }
                $count++;
                echo('<li><a href="index.php?assignment='.$lti->resource_link_id.'">'.htmlentities($lti->title).'</a>');
                echo("</li>\n");
            }
        }
    }
    if ( $count < 1 ) {
        echo("<p>No available assignments.</p>\n");
    } else {
        echo("</ul>\n");
    }
    echo("</div>\n");
} 

echo("</div>\n"); // myTabContent

$OUTPUT->footerStart();
// https://github.com/LinZap/jquery.waterfall
?>
<script type="text/javascript" src="<?= $CFG->staticroot ?>/js/waterfall-light.js"></script>
<script>
$(function(){
    $('#box').waterfall({refresh: 0})
});
</script>
<?php
$OUTPUT->footerend();