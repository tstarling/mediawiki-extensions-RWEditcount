<?php

class SpecialEditcount extends SpecialPage {
  
  var $target, $cutoff, $month, $year;
  
  function __construct() {
    parent::__construct('Editcount');
    #SpecialPage::setGroup('Editcount','users');
    global $wgSpecialPageGroups;
    $wgSpecialPageGroups['Editcount']='users';
    wfLoadExtensionMessages('Editcount');
  }
  
  function execute( $par ) {
    global $wgRequest, $wgOut, $wgUser;
    if( wfReadOnly() ) {
		  $wgOut->readOnlyPage();
		  return;
		}
		
    $this->target = $wgRequest->getVal('name', $par );
    $this->target = strtr( $this->target, '_', ' ' );
    $this->cutoff= $wgRequest->getVal('cutoff');
    $this->month= $wgRequest->getVal('month','__');
    $this->year= $wgRequest->getVal('year','____');
    
    if ($this->target) $this->cutoff = null;
    
		$this->setHeaders();

    $mAll = wfMsg('editcount-all');
    $currentYear = gmdate('Y');
    
    $yearoptions = '';
    
    for ($i = $currentYear; $i>= 2007; --$i)
    {
      $yearoptions .= "<option value=\"$i\" " . (($this->year == $i) ? "selected" : "") .">$i</option>\n";
    }
		
    $titleObject = $this->getTitle(); #For( 'Editcount' );
		$wgOut->addHTML(
       Xml::openElement('form', array( 'id' => 'editcount', 'method' => 'post', 'action' => $titleObject->getLocalURL("action=submit"), ) ) .
       Xml::openElement( 'fieldset' ) .
       Xml::element( 'legend', null, wfMsg( 'editcountlegend' ) ) .
       Xml::label( wfMsg('editcount-username'), 'name' ) . "&nbsp;" .
       Xml::input( 'name', 45, $this->target,
                    array( 'tabindex' => '1',
                           'id' => 'name', ) ) .
       "<p>" .
       Xml::label( wfMsg('editcount-month'), 'month' ) . "&nbsp;" .
		       "<select id=\"month\" name=\"month\" tabindex=\"2\">
             <option value=\"__\">{$mAll}</option>
             <option value=\"01\" " . (($this->month == '01') ? 'selected' : '') .">01</option>
             <option value=\"02\" " . (($this->month == '02') ? 'selected' : '') .">02</option>
             <option value=\"03\" " . (($this->month == '03') ? 'selected' : '') .">03</option>
             <option value=\"04\" " . (($this->month == '04') ? 'selected' : '') .">04</option>
             <option value=\"05\" " . (($this->month == '05') ? 'selected' : '') .">05</option>
             <option value=\"06\" " . (($this->month == '06') ? 'selected' : '') .">06</option>
             <option value=\"07\" " . (($this->month == '07') ? 'selected' : '') .">07</option>
             <option value=\"08\" " . (($this->month == '08') ? 'selected' : '') .">08</option>
             <option value=\"09\" " . (($this->month == '09') ? 'selected' : '') .">09</option>
             <option value=\"10\" " . (($this->month == '10') ? 'selected' : '') .">10</option>
             <option value=\"11\" " . (($this->month == '11') ? 'selected' : '') .">11</option>
             <option value=\"12\" " . (($this->month == '12') ? 'selected' : '') .">12</option>
		       </select> " .
		       Xml::label( wfMsg('editcount-year'), 'year' ) . "&nbsp;
		       <select id=\"year\" name=\"year\" tabindex=\"3\">
             <option value=\"____\">{$mAll}</option>
             {$yearoptions}
           </select>
        </p><p> " .
        Xml::label( wfMsg('editcount-returntop'), 'month' ) . "&nbsp;" .
        Xml::input( 'cutoff', 1, $this->cutoff,
                     array( 'tabindex' => '4',
                             'id' => 'cutoff',
                             'maxlength' => '3' ) ) . "
        </p><p> " .
        Xml::submitButton( wfMsg( 'editcount-go' ),
                           array('name' => 'editcount_go',
                                 'tabindex' => '5',
                                 'accesskey' => 's') ) . "
        </p>" .
        Xml::closeElement( 'fieldset' ) .
        Xml::closeElement( 'form' )
		);
		
		$like = "{$this->year}{$this->month}";
		$dbr = wfGetDB(DB_SLAVE);
		if ($this->target) 
		{
		  $conds = array(
		    'rev_user_text' => $this->target,
		    'rev_timestamp LIKE "' . /*$dbr->escapeLike(*/$like/*)*/ . '%"',
		  );
		  $res = $dbr->select(array('page', 'revision'),
		                      array('page_namespace','count(page_namespace)'),
		                      $conds,
		                      'SpecialEditcount::execute',
		                      array('GROUP BY' => 'page_namespace'),
		                      array('revision' => array('JOIN','page_id=rev_page')) );
      $total = 0;
      $data = array();
      while($row = $dbr->fetchRow($res))
      {
        $total += $row['count(page_namespace)'];
        $data[] = array('ns' => $row['page_namespace'],'count' => $row['count(page_namespace)']);
      }
      $res->free();
      $this->month = $this->month == '__' ? '*' : $this->month;
      $this->year = $this->year == '____' ? '*' : $this->year;
      $totallabel = wfMsg('editcount-total');
      $wgOut->addHTML("<table>
        <tr>
          <td style='padding-right:4em;'>{$this->target}</td>
          <td style='padding-right:1em;'>{$this->month}/{$this->year}</td>
          <td>$totallabel&nbsp;{$total}</td>
        </tr>
        "
      );
      global $wgCanonicalNamespaceNames;
      for($i=0; $i<count($data);++$i)
      {
        $ns = ($data[$i]['ns'] == NS_MAIN) ? wfMsg(blanknamespace) : strtr( $wgCanonicalNamespaceNames[$data[$i]['ns']], '_', ' ' );;
		    $num = $data[$i]['count'];
		    $perc  = round($num/$total*100,2);
		    $wgOut->addHTML("
          <tr>
		        <td>{$ns}</td>
	          <td>{$num}</td>
	          <td>{$perc}%</td>
          </tr>
  		    "
  		  );
		  }
		  $wgOut->addHTML('</table>');
		} elseif ($this->cutoff) {
		  $res=$dbr->select('revision',
		                    array('rev_user_text','count(rev_timestamp)'),
		                    array('rev_timestamp LIKE "' . /*$dbr->escapeLike(*/$like/*)*/ . '%"',),
		                    'SpecialEditcount::execute',
		                    array('GROUP BY' => 'rev_user_text', 'ORDER BY' => 'count(rev_timestamp) desc', 'LIMIT' => "{$this->cutoff}")
		                    );
      $wgOut->addHTML("<table>");
      $mmonth = $this->month == '__' ? '*' : $this->month;
      $myear = $this->year == '____' ? '*' : $this->year;
		  while($row = $dbr->fetchRow($res))
      {
        $name = $row['rev_user_text'];
        $total = $row['count(rev_timestamp)'];
        $userlink = Xml::openElement('a',array('href' => $titleObject->getLocalURL(array('name' => $name,
                                                                                  'year' => $this->year,
                                                                                  'month' => $this->month)) )) . 
                      $name . 
                    Xml::closeElement('a');
        $wgOut->addHTML("
        <tr>
          <td style='padding-right:4em;'>{$userlink}</td>
          <td style='padding-right:1em;'>{$mmonth}/{$myear}</td>
          <td>$totallabel&nbsp;{$total}</td>
        </tr>
        "
        );
      }
      $wgOut->addHTML("</table>");
		}
  }
}

class ApiActiveusers extends ApiQueryBase {
  public function __construct($query, $moduleName) {
    parent::__construct($query,$moduleName,'ac');
  }
  
  public function execute() {
    $db = $this->getDB();
    $params = $this->extractRequestParams();
    
    $month = $params['month'];
    if (!is_null($month))
    {
      $month = str_pad($month,2,'0',STR_PAD_LEFT);
    }
    
    $year = $params['year'];
    $limit = $params['limit'];
    $this->addTables('revision');
    
    //"rev_timestamp like \"{$this->year}{$this->month}%\""
    
    if (!is_null($year) && !is_null($month)) {
      $this->addWhere( 'rev_timestamp' . $this->getDB()->buildLike( "{$year}{$month}", $this->getDB()->anyString() ) );
    } elseif (!is_null($year)) {
      $this->addWhere( 'rev_timestamp' . $this->getDB()->buildLike( "{$year}", $this->getDB()->anyString() ) );
    } elseif (!is_null($month)) {
      $this->addWhere( 'rev_timestamp' . $this->getDB()->buildLike( $this->getDB()->anyChar(), $this->getDB()->anyChar(), $this->getDB()->anyChar(), $this->getDB()->anyChar(),
                                                                    $month, $this->getDB()->anyString() ) );
    }

    $this->addFields( array('rev_user_text','count(rev_timestamp)') );
    $this->addOption('GROUP BY', 'rev_user_text');
    $this->addOption('ORDER BY', "count(rev_timestamp) desc");
    $this->addOption('LIMIT', "{$limit}");
    
    $res = $this->select(__METHOD__);
    $result = $this->getResult();
    
    $data = array();
    
    while ($row = $db->fetchRow($res)) {
      $data[] = array('name' => $row['rev_user_text'], 'editcount' => $row['count(rev_timestamp)']);
    }
    $db->freeResult($res);
    
    $result->setIndexedTagName($data, 'u');
    $result->addValue('query',$this->getModuleName(),$data);
    
    
    
  }
  
  public function getAllowedParams() {
    return array (
      'month' => null,
      'year' => null,
      'limit' => array (
        ApiBase :: PARAM_DFLT => 10,
        ApiBase :: PARAM_TYPE => 'limit',
        ApiBase :: PARAM_MIN => 1,
        ApiBase :: PARAM_MAX => ApiBase :: LIMIT_BIG1,
        ApiBase :: PARAM_MAX2 => ApiBase :: LIMIT_BIG2
      )
    );
  }
  
  public function getParamDescription() {
    return array (
      'month' => 'Get contributions for this month only',
      'year' => 'Get contributions for this year only',
			'limit' => 'How many total user names to return.',
    );
  }
  
  public function getDescription() {
    return 'Enumerate active users';
  }
  
  public function getExamples() {
    return array(
      'api.php?action=query&list=activeusers&aclimit=150',
      'api.php?action=query&list=activeusers&aclimit=150&acmonth=03&acyear=2009',
    );
  }
  
  public function getVersion() {
    return "1.0";
  }
}
