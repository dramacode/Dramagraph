<?php
// declare(encoding = 'utf-8');
setlocale(LC_ALL, 'fr_FR.utf8');
mb_internal_encoding("UTF-8");

if (realpath($_SERVER['SCRIPT_FILENAME']) != realpath(__FILE__)); // file is include do nothing
else if (php_sapi_name() == "cli") { Dramagraph_Biblio::cli(); }

class Dramagraph_Biblio {

  /**
   *
   */
  public static function csv( $pdo, $cols = null )
  {
    $csv = array();
    if ( !$cols && !is_array($cols) ) {
      $cols = array(
        'author',
        'date',
        'genre',
        'verse',
        'spavg',
        'roleavg',
        'title',
      );
    }
    $cell = array();
    foreach ($cols as $key) {
      $cell[] = $key;
    }
    $csv[] = implode( "\t", $cell );
    foreach ($pdo->query("SELECT * FROM play ORDER BY author, date") as $row) {
      if (!$row['c']) continue; // pièce boguée
      $cell = array();
      foreach ($cols as $key) {
        if ( 'spavg' == $key ) $cell[] = $row['c']/$row['sp']/60;
        else if ( 'roleavg' == $key ) $cell[] = $row['cspeakers']/$row['c'];
        else $cell[] = $row[$key];
      }
      $csv[] = implode( "\t", $cell );
    }
    return implode ("\n", $csv);
  }
  /**
   * Ligne bibliographique pour une pièce
   */
  public static function bibl($pdo, $play)
  {
    if (is_string($play)) {
      $playcode = $pdo->quote($playcode);
      $play = $pdo->query("SELECT * FROM play WHERE code = $playcode")->fetch();
    }
    $bibl = $play['author'].', '.$play['title'];
    $meta = array();
    if ($play['created']) $meta[] = $play['created'];
    if ($play['issued']) $meta[] = $play['issued'];
    if ($play['genre'] == 'tragedy') $meta[] = 'tragédie';
    else if ($play['genre'] == 'comedy') $meta[] = 'comédie';
    if ($play['acts']) $meta[] = $play['acts'].(($play['acts']>2)?" actes":" acte");
    $meta[] = (($play['verse'])?"vers":"prose");
    if (count($meta)) $bibl .= " (".implode(", ", $meta).")";
    return $bibl;
  }


  /**
   * Table bibliographique des pièces en base
   */
  public static function table( $pdo, $cols = null, $linkf = "%s" )
  {
    if ( !$cols && !is_array($cols) ) {
      $cols = array(
        'n',
        'author',
        'created',
        'issued',
        'title',
        'c',
        'sp',
        'spavg',
        'role',
        'roleavg',
        'publisher'
      );
    }
    echo '      <table class="sortable" align="center">'."\n";
    echo '        <tr>'."\n";
    foreach ($cols as $key) {
      if ( 'n' == $key)
        echo '          <th>N°</th>'."\n";
      if ( 'author' == $key)
        echo '          <th>Auteur</th>'."\n";
      else if ( 'created' == $key)
        echo '          <th title="Date de création">Créé</th>'."\n";
      else if ( 'issued' == $key)
        echo '          <th title="Date de publication">Publié</th>'."\n";
      else if ( 'title' == $key)
        echo '          <th>Titre</th>'."\n";
      else if ( 'c' == $key)
        echo '          <th title="Quantité de texte prononcé en lignes (60 signes).">Paroles</th>'."\n";
      else if ( 'sp' == $key)
        echo '          <th title="Nombre de répliques.">Répliques</th>'."\n";
      else if ( 'spavg' == $key)
        echo '          <th title="Taille moyenne d’une réplique, en lignes (60 signes).">Rép. moy.</th>'."\n";
      else if ( 'role' == $key)
        echo '          <th title="Nombre de personnages déclarés dans la distribution.">Pers.</th>'."\n";
      else if ( 'roleavg' == $key)
        echo '          <th title="Nombre moyen de personnages parlants sur scène.">Prés. moy.</th>'."\n";
      else if ( 'publisher' == $key)
        echo '          <th>Éditeur</th>'."\n";
    }
    echo '        </tr>'."\n";
    $n = 0;
    foreach ($pdo->query("SELECT * FROM play ORDER BY author, created, issued") as $row) {
      if (!$row['c']) continue; // pièce boguée
      $n++;
      echo '        <tr>'."\n";
      foreach ($cols as $key) {
        if ( 'n' == $key)
          echo '          <td>'.$n.'</td>'."\n";
        if ( 'author' == $key)
          echo '          <td>'.$row['author'].'</td>'."\n";
        else if ( 'created' == $key)
          echo '          <td>'.$row['created'].'</td>'."\n";
        else if ( 'issued' == $key)
          echo '          <td>'.$row['issued'].'</td>'."\n";
        else if ( 'title' == $key) {
          $href = sprintf( $linkf, $row['code'] );
          echo '          <td>'.'<a href="'.$href.'">'.$row['title']."</a></td>\n";
        }
        else if ( 'c' == $key)
          echo '          <td align="right">'.number_format($row['c']/60, 0, ',', ' ').' l.</td>';
        else if ( 'sp' == $key)
          echo '          <td align="right">'.$row['sp'].'</td>';
        else if ( 'spavg' == $key)
          echo '          <td align="right">'.number_format($row['c']/$row['sp']/60, 2, ',', ' ').' l.</td>';
        else if ( 'role' == $key)
          echo '          <td align="right">'.$row['roles'].'</td>';
        else if ( 'roleavg' == $key)
          echo '          <td align="right">'.number_format($row['cspeakers']/$row['c'], 1, ',', ' ').' pers.</td>';
        else if ( 'publisher' == $key) {
          if ( $pos = strpos( $row['publisher'], '(' ) ) $row['publisher'] = trim( substr( $row['publisher'], 0, $pos) );
          if ($row['identifier']) echo '          <td><a href="'.$row['identifier'].'">'.$row['publisher'].'</a></td>'."\n";
          else echo '          <td>'.$row['publisher'].'</td>'."\n";
        }
      }
      echo '        </tr>'."\n";
    }
    echo '</table>'."\n";
  }

   /**
    * Liste de pièce comme un <select>
    */
   public static function select( $pdo, $playcode=null )
   {
     $html = array();
     $html[] = '       <select name="play" onchange="if (this.form.onsubmit) this.form.onsubmit(); else this.form.submit(); ">';
     $html[] = '         <option value=""> </option>';
     foreach ($pdo->query("SELECT * FROM play ORDER BY author, created, issued") as $row) {
       if ($row['code'] == $playcode) $selected=' selected="selected"';
       else $selected = "";
       if ($row['created'] && $row['issued']) $date = " (".$row['created'].", ".$row['issued'].") ";
       else if ($row['created']) $date = " (".$row['created'].") ";
       else if ($row['issued']) $date = " (".$row['issued'].") ";
       else $date = ' ; ';
       $title = $row['title'];
       $max = 100;
       if ( strlen( $title ) > $max ) {
         $pos = strpos( $title, ' ', $max);
         if ( $pos ) $title = substr( $title, 0, $pos );
       }
       $html[] = '         <option value="'.$row['code'].'"'.$selected.'>'.$row['author'].$date.$title."</option>";
     }
     $html[] = '       </select>';
     return implode("\n", $html);
   }

   /**
    * Sortir des stats directemnt
    */
    static function cli()
    {
      $timeStart = microtime(true);
      $usage = '
      usage    : php -f '.basename(__FILE__).' base.sqlite '."\n\n";
      array_shift($_SERVER['argv']); // shift first arg, the script filepath
      if (!count($_SERVER['argv'])) exit($usage);
      $pdo = new PDO('sqlite:'.$_SERVER['argv'][0]);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
      echo self::csv($pdo);
    }
}
?>
