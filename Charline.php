<?php

class Dramagraph_Charline {

  /**
   * Bande rythmique d’une pièce
   */
  public static function rythm($pdo, $p=array()) {
    $p = array_merge(array(
      'playcode' => null,
      'refsize' => 800,
      'prehref' => '',
      'target' => '', // iframe target
    ), $p);
    if ($p['target']) $p['target'] = ' target="'.$p['target'].'"';
    $play = $pdo->query("SELECT * FROM play where code = ".$pdo->quote($p['playcode']))->fetch();
    $playid = $play['id'];
    if (!$play) return false;
    $qconf = $pdo->prepare("SELECT * FROM configuration WHERE act = ? ORDER BY rowid; ");
    $qcn = $pdo->prepare("SELECT * FROM sp WHERE configuration = ? AND cn <= ? ORDER BY play, cn DESC LIMIT 1");
    echo '<table class="dramarythm">'."\n";

    // boucler sur les actes une fois pour les intitulés, puis une deuxième pour remplir
    echo '  <tr>'."\n";
    foreach ($pdo->query("SELECT * FROM act WHERE play = ".$play['id']." ORDER BY rowid") as $act) {
      $actsize = ceil($act['c']*$p['refsize']/100000);
      echo '    <td class="label"><div title="'.$act['label'].'" style="width: '.$actsize.'px">'.$act['label']."</div></td>\n";
    }
    echo "  </tr>\n";
    echo "  <tr>\n";
    foreach ($pdo->query("SELECT * FROM act WHERE play = ".$play['id']." ORDER BY rowid") as $act) {
      if(!$act['c']) continue;
      echo '    <td class="act">'."\n";
      echo '      <table class="act"><tr>'."\n";
      $qconf->execute(array($act['id']));
      $conf1 = true;
      while ($conf = $qconf->fetch()) {
        if (!$conf['c']) continue; // conf sans parole
        $confsize = ceil($conf['c']*$p['refsize']/100000/3)*3;
        // step in chars, relative to desired width (min=1) and size of conf in chars
        // ~350 or less when conf is short
        $cstep = floor($conf['c'] / floor($confsize / 3));
        $tdclass = ' class="bleft"';
        $tot = 0; // see if we all sp
        // take first $sp
        $cn = $conf['cn'];
        $qcn->execute( array($conf['id'], $cn));
        $splast = $qcn->fetch();
        $first = 1;
        while ($cn < $conf['cn']+$conf['c']) {
          $cn = $cn + $cstep;
          $qcn->execute( array($conf['id'], $cn));
          $sp = $qcn->fetch();
          $dif = $first + $sp['id'] - $splast['id'];
          if ($dif>15) $dif = 15;
          $bclass = 'sp'.$dif;
          echo '<td'.$tdclass.'><b class="'.$bclass.'"> </b></td>';
          $tdclass='';
          $tot += $dif;
          $splast = $sp;
          $first = 0;
        }
      }

      echo '</tr></table>'."\n";
      echo '</td>'."\n";
    }
    echo '</tr></table>'."\n";
  }

  public static function poph( $value )
  {
    return round( 50*log( 10*$value ) ) - 100;
  }

  /**
   * Population of speakers on scene
   */
  public static function population( $pdo, $playcode )
  {
    $play = $pdo->query( "SELECT * FROM play where code = ".$pdo->quote( $playcode ) )->fetch();
    if (!$play) return false;
    // table nb pers => chars
    $pers = array();
    $html = array();
    $max = 0;
    $max;
    $html[] = '<div class="dramapop">';
    $html[] = '  <div class="right">Nombre de personnages parlants sur scène : ordre temporel et ordre croissant  </div>';
    $html[] = '  <table class="bars">';
    $html[] = '    <tr>';
    // loop on all configurations
    foreach ( $pdo->query( "SELECT * FROM configuration WHERE play = ".$play['id'] ) as $conf ) {
      if ( $conf['speakers'] > $max ) $max = $conf['speakers'];
      $width = ceil( 1000 * $conf['c'] / $play['c'] ) / 10;
      $html[] = '      <td width="'.$width.'%"><div class="bar" style="height: '.( self::poph( $conf['speakers'] ) ).'px"></div></td>';
      if ( !isset( $pers[$conf['speakers']] ) ) $pers[$conf['speakers']] = $conf['c'];
      else $pers[$conf['speakers']] += $conf['c'];
    }
    $html[] = '    </tr>';
    $html[] = '  </table>';
    // horizontal grid
    $html[] = '  <div class="hgrid">';
    for ( $i = 1; $i <= $max; $i++ ) {
      $html[] = '    <div class="line" style="bottom:'.self::poph($i).'px; padding-left: '. $i .'em">'.$i.'</div>';
    }
    $html[] = '  </div>';
    // cumulative curve
    $html[] = '  <table class="steps">';
    $html[] = '    <tr>';
    for ( $i = 1; $i <= $max; $i++ ) {
      if ( !isset($pers[$i]) ) continue;
      $width = ceil( 1000 * $pers[$i] / $play['c'] ) / 10;
      $html[] = '    <td width="'.$width.'%"><div class="step" style="height: '.self::poph($i).'px;"></div></td>';
    }
    $html[] = '    </tr>';
    $html[] = '  </table>';

    $html[] = '</div>';
    return implode("\n", $html);
  }


  /**
   * Panneau vertical de pièce
   */
  public static function pannel($pdo, $p=array()) {
    $p = array_merge(array(
      'playcode' => null,
      'width' => 230,
      'refsize' => 600,
      'rythm' => false,
      'prehref' => '',
      'target' => '', // iframe target
    ), $p);


    if ($p['target']) $p['target'] = ' target="'.$p['target'].'"';
    $play = $pdo->query("SELECT * FROM play where code = ".$pdo->quote($p['playcode']))->fetch();
    $playid = $play['id'];
    if (!$play) return false;
    $confwidth = $p['width'] - 75;


    // 1 pixel = 1000 caractères
    if (!$p['refsize']) $playheight = '800';
    else if (is_numeric($p['refsize']) && $p['refsize'] > 50) $playheight = round($play['c'] / (100000/$p['refsize']));
    else $playheight = '800';
    $csize = $playheight/100000; // largeur moyenne pour un caractère

    $html = array();

    // requête sur le nombre de caractères d’un rôle dans une scène
    $qsp = $pdo->prepare("SELECT role.*, SUM(sp.c) AS ord FROM sp, role WHERE configuration = ? AND sp.role = role.id GROUP BY role ORDER BY ord DESC");
    $qcn = $pdo->prepare("SELECT * FROM sp WHERE configuration = ? AND cn <= ? ORDER BY cn DESC LIMIT 1");
    $qscene = $pdo->prepare("SELECT * FROM scene WHERE id = ?");
    $html[] = '<div class="charline">';

    // loop on acts
    foreach ($pdo->query("SELECT * FROM act WHERE play = $playid ORDER BY rowid") as $act) {
      $html[] = '  <a'.$p['target'].' href="'.$p['prehref'].'#'.$act['code'].'" class="acthead">'.$act['label']."</a>";
      if(!$act['c']) continue; // probably an interlude
      $html[] =  '  <div class="act">';
      $actheight = $playheight * $act['c']/$play['c'];
      $sceneid = null;
      $scene = null;
      // loop on configurations
      foreach ($pdo->query("SELECT * FROM configuration WHERE act = ".$act['id']) as $conf) {
        if(!$conf['c']) continue; // configuration with no sp, probably in <stage>
        $confsize = 3+ ceil($actheight * $conf['c']/$act['c']);
        if (!isset($conf['n'])) $conf['n'] = 0+ preg_replace('/\D/', '', $conf['code']);
        // new scene label (if there)
        if($sceneid != $conf['scene']) {
          $sceneid = $conf['scene'];
          $qscene->execute(array($conf['scene']));
          $scene = $qscene->fetch();
          if ($scene) $html[] = '      <b class="n">'.$scene['n'].'</b>';
        }
        // Configuration content
        $title = 'Acte '.$act['n'];
        if ($scene) $title .= ', scène '.$scene['n'];

        if ( $p['rythm'] ) $html[] =  '    <div class="conf" style="height: '.($confsize +1).'px;" title="'.$title.'">';

        // role bar
        $html[] =  '    <a class="cast"'.$p['target'].' href="'.$p['prehref'].'#'.$conf['code'].'" style="height: '.($confsize +1).'px;" title="'.$title.'">';
        $qsp->execute(array($conf['id']));
        // loop on role
        while ($role = $qsp->fetch()) {
          if (!$role['ord']) continue;
          $rolewidth = number_format(99 * $role['ord'] / $conf['c'], 1, '.', '') ;
          $span = '<span class="role '.$role['rend'].'"';
          $span .= ' style="width: '.$rolewidth.'%"';
          $title = $role['label'].', acte '.$act['n'];
          if ($scene) $title .= ', scène '.$scene['n'];
          $span .= ' title="'.$title.', '.round(100*$role['ord'] / $conf['c']).'%"';
          $span .= '>';
          if ($rolewidth > 35 && $confsize > 12 ) { // && !isset($list[$role['code']])
            $span .= '<span>'.$role['label'].'</span>';
            $list[$role['code']] = true;
          }
          else $span .= ' ';
          $span .= '</span>';
          $html[] = $span;
        }
        $html[] = "      </a>";

        if ( $p['rythm'] ) {
          // rythm
          $html[] = '      <div class="sps">';
          // courir par 3 pixels, ne pas oublier floor, si la hauteur n’est pas multiple de 3
          $cstep = floor($conf['c'] / floor($confsize / 3));
          // take first $sp
          $cn = $conf['cn'];
          $qcn->execute( array($conf['id'], $cn));
          $splast = $qcn->fetch();
          $first = 1;
          while ($cn < $conf['cn']+$conf['c']) {
            $cn = $cn + $cstep +1;
            $qcn->execute( array($conf['id'], $cn));
            $sp = $qcn->fetch();
            $dif = $first + $sp['id'] - $splast['id'];
            if ($dif>15) $dif = 15;
            $html[] = '<a'.$p['target'].' href="'.$p['prehref'].'#'.$splast['code'].'"><b style="width: '.($dif*3).'px"> </b></a>';
            $splast = $sp;
            $first = 0;
          }
          $html[] = "      </div>";
          $html[] = "    </div>";
        }
      }
      $html[] = "  </div>";
    }
    $html[] = "</div>";
    return implode("\n", $html);
  }

}

?>
