<?php
// In the top frame, we use cookies for session.
if ( ! defined('COOKIE_SESSION') ) define('COOKIE_SESSION', true);
require_once("../../config.php");
require_once("../../admin/admin_util.php");
require_once("expire_util.php");

use \Tsugi\UI\Table;
use \Tsugi\Util\U;
use \Tsugi\Core\LTIX;

\Tsugi\Core\LTIX::getConnection();

session_start();

require_once("../gate.php");
if ( $REDIRECTED === true || ! isset($_SESSION["admin"]) ) return;

if ( ! ( isset($_SESSION['id']) || isAdmin() ) ) {
    $_SESSION['login_return'] = LTIX::curPageUrlFolder();
    header('Location: '.$CFG->wwwroot.'/login');
    return;
}

$tenant_count = get_count_table('lti_key');
$context_count = get_count_table('lti_context');
$user_count = get_count_table('lti_user');

$tenant_days = U::get($_GET,'tenant_days',1000);
$context_days = U::get($_GET,'context_days',500);
$user_days = U::get($_GET,'user_days',500);
$pii_days = U::get($_GET,'pii_days',120);

$user_expire =  get_expirable_records('lti_user', $user_days);
$context_expire =  get_expirable_records('lti_context', $context_days);
$tenant_expire =  get_expirable_records('lti_key', $tenant_days);
$pii_expire =  get_pii_count($pii_days);

$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav();
$OUTPUT->flashMessages();
?>
<div id="iframe-dialog" title="Read Only Dialog" style="display: none;">
   <img src="<?= $OUTPUT->getSpinnerUrl() ?>" id="iframe-spinner"><br/>
   <iframe name="iframe-frame" style="height:600px" id="iframe-frame"
    onload="document.getElementById('iframe-spinner').style.display='none';">
   </iframe>
</div>
<h1>Manage Data Expiry</h1>
<p>
  <a href="<?= LTIX::curPageUrlFolder() ?>" class="btn btn-default active">Summary</a>
  <a href="pii-detail" class="btn btn-default">PII Detail</a>
  <a href="<?= $CFG->wwwroot ?>/admin" class="btn btn-default">Admin</a>
</p>
<form>
<ul>
<li>User count: <?= $user_count ?>  <br/>
<ul>
<li>
Users with PII and no activity in
<input type="text" name="pii_days" size=5 class="auto_days" value="<?= $pii_days ?>"> days:
<?= $pii_expire ?>
<?php if ( $pii_expire > 0 ) { ?>
  <br/><a href="#" title="Expire PII" class="auto_expire btn btn-xs btn-warning"
  onclick="showModalIframeUrl(this.title, 'iframe-dialog', 'iframe-frame', 'expire-pii?pii_days=<?= $pii_days ?>', _TSUGI.spinnerUrl, true); return false;" >
  Expire PII &gt; <?= $pii_days ?> Days
  </a>
<?php } ?>
</li>
<li>
Users with no activity in
<input type="text" name="user_days" size=5 class="auto_days" value="<?= $user_days ?>"> days:
<?= $user_expire ?>
<?php if ( $user_expire > 0 ) { ?>
  <br/><a href="#" title="Expire Users" class="auto_expire btn btn-xs btn-warning"
  onclick="showModalIframeUrl(this.title, 'iframe-dialog', 'iframe-frame', 'expire-user?user_days=<?= $user_days ?>', _TSUGI.spinnerUrl, true); return false;" >
  Expire Users &gt; <?= $user_days ?> Days
  </a>
<?php } ?>
</li>
</ul>
<li>Context count: <?= $context_count ?>  <br/>
Contexts with no activity in
<input type="text" name="context_days" size=5 class="auto_days" value="<?= $context_days ?>"> days:
<?= $context_expire ?>
<?php if ( $context_expire > 0 ) { ?>
  <br/><a href="#" title="Expire Contexts" class="auto_expire btn btn-xs btn-warning"
  onclick="showModalIframeUrl(this.title, 'iframe-dialog', 'iframe-frame', 'expire-context?context_days=<?= $context_days ?>', _TSUGI.spinnerUrl, true); return false;" >
  Expire Contexts &gt; <?= $context_days ?> Days
  </a>
<?php } ?>
</li>
<li>Tenant count: <?= $tenant_count ?>  <br/>
Tenants with no activity in
<input type="text" name="tenant_days" size=5 class="auto_days" value="<?= $tenant_days ?>"> days:
<?= $tenant_expire ?>
<?php if ( $tenant_expire > 0 ) { ?>
  <br/><a href="#" title="Expire Tenants" class="auto_expire btn btn-xs btn-warning"
  onclick="showModalIframeUrl(this.title, 'iframe-dialog', 'iframe-frame', 'expire-tenant?tenant_days=<?= $tenant_days ?>', _TSUGI.spinnerUrl, true); return false;" >
  Expire Tenants &gt; <?= $tenant_days ?> Days
  </a>
<?php } ?>
</li>
</ul>
<input type="submit" value="Update">
</form>
<?php
$OUTPUT->footerStart();
?>
<script>
$('.auto_days').on('change', function() {
  $(".auto_expire").hide();
  $(this).closest('form').submit();
});
</script>
<?php
$OUTPUT->footerEnd();
