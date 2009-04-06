<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once('include/global.php');

$escRealm = Topos::escape_string($TOPOS_REALM);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ( empty($_POST['pool']) ||
       empty($_POST['tokens']) )
    Topos::fatal('BAD_REQUEST', 'Missing one or more required parameters');
  $pool = $_POST['pool'];
  $tokens = (int)($_POST['tokens']);
  if ( !preg_match('/^[\\w\\-.]+$/', $pool) ||
       !$tokens || $tokens > 1000000)
    Topos::fatal('BAD_REQUEST', 'Illegal parameter value(s)');
  $escPoolName = Topos::escape_string($pool);
  Topos::real_query(
    "CALL `createTokens`({$escRealm}, {$escPoolName}, {$tokens});"
  );
  Topos::log('populate', array(
    'realmName' => $TOPOS_REALM,
    'poolName' => $TOPOS_POOL,
    'tokens' => $tokens
  ));
  REST::header(array(
    'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
  ));
  Topos::start_html('Realm');
  echo '<p>Pool populated successfully.</p>' .
       '<p><a href="./" rel="index">Back</a></p>';
  Topos::end_html();
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  Topos::real_query('START TRANSACTION;');
  try {
    Topos::real_query(<<<EOS
DELETE `Tokens`.* FROM `Tokens` NATURAL JOIN `Pools`
WHERE `Pools`.`realmName` = {$escRealm};
EOS
    );
    Topos::log('delete', array(
      'realm' => $TOPOS_REALM,
      'tokens' => Topos::mysqli()->affected_rows
    ));
  }
  catch (Topos_MySQL $e) {
    Topos::mysqli()->rollback();
    throw $e;
  }
  if (!Topos::mysqli()->commit())
    Topos::fatal(
      'SERVICE_UNAVAILABLE',
      'Transaction failed: ' . htmlentities( Topos::mysqli()->error )
    );
  REST::header(array(
    'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
  ));
  Topos::start_html('Pool');
  echo '<p>Realm destroyed successfully.</p>';
  Topos::end_html();
  exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  Topos::fatal('NOT_MODIFIED');

REST::header(array(
  'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8',
  'Last-Modified' => REST::http_date(0),
));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
Topos::start_html('Realm');
?><h1>Forms</h1>
<h2>Delete</h2>
<form action="./?http_method=DELETE" method="post">
<input type="submit" value="Delete this realm"/>
</form>
<h2>Populate new pool</h2>
<form action="./" method="post">
<input type="text" name="pool"/> Pool name<br/>
<input type="text" name="tokens"/> #tokens<br/>
<input type="submit" value="Populate"/>
</form>
<h2>Getting the next token</h2>
<form action="nextToken" method="get">
<input type="text" name="pool"/> Pool name RegExp<br/>
<input type="text" name="token"/> Token value RegExp<br/>
<input type="text" name="timeout"/> Timeout in seconds (leave empty for shared tokens)<br/>
<input type="submit" value="Get next token"/>
</form>
<h1>Directory index</h1><?php
Topos::directory_list(array(
  array(
    'name' => 'locks/',
    'desc' => 'A locks directory',
  ),
  array(
    'name' => 'pools/',
    'desc' => 'A pools directory',
  ),
  array(
    'name' => 'nextToken',
    'desc' => 'GET or PUT the next token',
  )
));
Topos::end_html();

?>