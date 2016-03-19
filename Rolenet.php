<?php
/**
 * Visualisation relatives au réseaux de parole
 */
class Dramaturgie_Rolenet {
  /** Lien à une base SQLite, unique */
  public $pdo;
  /** Couleurs pour le graphe, la clé est une classe de nœud, les valeurs son 1: nœud, 2: lien */
  public static $colors = array(
    1 => array("#FF4C4C", "rgba(255, 0, 0, 0.5)"),
    2 => array("#A64CA6", "rgba(128, 0, 128, 0.5)"),
    3 => array("#4C4CFF", "rgba(0, 0, 255, 0.5)"),
    4 => array("#4c4ca6", "rgba(0, 0, 128, 0.5)"),
    5 => array("#A6A6A6", "rgba(140, 140, 160, 0.6)"),
    "female" => array("#FF4C4C", "rgba(255, 0, 0, 0.5)"),
    "female superior" => array("#FF0000", "rgba(255, 0, 0, 0.5)"),
    "female junior" => array("#FFb0D0", "rgba(255, 128, 192, 0.5)"),
    "female inferior" => array("#D07070", "rgba(192, 96, 96, 0.3)"),
    "female veteran" => array("#903333", "rgba(128, 0, 0, 0.3)"),
    "male" => array("#4C4CFF", "rgba(0, 0, 255, 0.3)"),
    "male junior" => array("#B0D0FF", "rgba(128, 192, 255, 0.5)"),
    "male veteran" => array("#333390", "rgba(0, 0, 128, 0.3)"),
    "male superior" => array("#0000FF", "rgba(0, 0, 255, 0.3)"),
    "male inferior" => array("#C0C0FF", "rgba(96, 96, 192, 0.3)"),
    "male exterior" => array("#A0A0A0", "rgba(96, 96, 192, 0.3)"),
  );
  /** Se lier à la base */
  public function __construct($sqlitefile) {
    // ? pouvois passer un pdo ?
    $this->pdo = new PDO('sqlite:'.$sqlitefile);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  }
  /**
   * Html for canvas
   */
  public function canvas($id='graph') {
    $html = '
    <div id="'.$id.'" oncontextmenu="return false">
      <div class="sans-serif" style="position: absolute; top: 0; left: 1ex; font-size: 70%; ">Clic droit sur un nœud pour le supprimer</div>
      <div style="position: absolute; bottom: 0; right: 2px; z-index: 2; ">
        <button class="colors but" title="Gris ou couleurs">◐</button>
        <button class="shot but" type="button" title="Prendre une photo">📷</button>
        <button class="zoomin but" style="cursor: zoom-in; " type="button" title="Grossir">+</button>
        <button class="zoomout but" style="cursor: zoom-out; " type="button" title="Diminuer">-</button>
        <button class="but restore" type="button" title="Recharger">O</button>
        <button class="mix but" type="button" title="Mélanger le graphe">♻</button>
        <button class="grav but" type="button" title="Démarrer ou arrêter la gravité">►</button>
        <span class="resize interface" style="cursor: se-resize; font-size: 1.3em; " title="Redimensionner la feuille">⬊</span>
      </div>
    </div>
    ';
    return $html;
  }

  /**
   * Json compatible avec la librairie sigma.js
   */
  public function sigma($playcode) {
    $nodes = $this->nodes($playcode, 'act');
    $edges = $this->edges($playcode, 'act');
    $html = array();
    $html[] = "{ ";
    $html[] = "edges: [";
    for ($i=0; $i < count($edges); $i++) {
      $edge = $edges[$i];
      if (!isset($nodes[$edge['source']])) continue;
      if (!isset($nodes[$edge['target']])) continue;
      if ($i) $html[] = ",\n    ";
      $source = $nodes[$edge['source']];
      $col = "";
      if (isset(self::$colors[$source['class']])) {
        $col = ', color: "'.self::$colors[$source['class']][1].'"';
      }
      else if (isset(self::$colors['role'.$i])) {
        $col = ', color: "'.self::$colors[$source['rank']][1].'"';
      }

      $html[] = '{id:"e'.$i.'", source:"'.$edge['source'].'", target:"'.$edge['target'].'", size:"'.$edge['c'].'"'.$col.', type:"drama"}';

    }
    $html[] = "\n  ]";

    $html[] = ",";

    $html[] = "\n  nodes: [\n    ";


    $count = count($nodes);
    $i = 1;
    foreach ($nodes as $code=>$node) {
      if (!$code) continue;
      if ($i > 1) $html[] = ",\n    ";
      // position initiale en cercle, à 1h30
      $angle =  -M_PI - (M_PI*2/$count) *  ($i-1);
      // $angle =  2*M_PI/$count * ($i -1);
      $x =  number_format(6.0*cos($angle), 4);
      $y =  number_format(6.0*sin($angle), 4);
      /*
      // position initiale en ligne
      // $x = $i ;
      $y = 1;
      // $x = -$i*(1-2*($i%2));
      $x=$i;
      */
      $col = "";

      if (isset(self::$colors[$node['class']])) {
        $col = ', color: "'.self::$colors[$node['class']][0].'"';
      }
      else if (isset(self::$colors['role'.$i])) {
        $col = ', color: "'.self::$colors[$node['class']][0].'"';
      }
      // $json_options = JSON_UNESCAPED_UNICODE; // incompatible 5.3
      $json_options = null;
      $html[] = "{id:'".$node['code']."', label:".json_encode($node['label'],  $json_options).", size:".(0+$node['c']).", x: $x, y: $y".$col.", title: ".json_encode($node['title'],  $json_options).', type:"drama"}';
      $i++;
    }
    $html[] = "\n  ]";

    $html[] = "\n};\n";
    return implode("\n", $html);
  }

  /**
   * Produire fichier de nœuds et de relations
   * TODO, à vérifier
   */
  public function gephi($filename) {
    $data = $this->nodes($filename);
    $f = $filename.'-nodes.csv';
    $w = fopen($f, 'w');
    for ($i=0; $i<count($data); $i++) {
      fwrite($w, implode("\t", $data[$i])."\n");
    }
    fclose($w);
    echo $f.'  ';
    $data = $this->edges($filename);
    $f = $filename.'-edges.csv';
    $w = fopen($f, 'w');
    for ($i=0; $i<count($data); $i++) {
      fwrite($w, implode("\t", $data[$i])."\n");
    }
    fclose($w);
    echo $f."\n";
  }
  /**
   * Table des relations
   */
  public function edgetable ($playcode) {
    $html = array();
    $html[] = '
<table class="sortable">
  <tr>
    <th>N°</th>
    <th>De</th>
    <th>À</th>
    <th>Scènes</th>
    <th>Paroles</th>
    <th>Répliques</th>
    <th>Rép. moy.</th>
  </tr>
  ';
    $edges = $this->edges($playcode);
    foreach ($edges as $key => $edge) {
      $html[] = "  <tr>";
      $html[] = '    <td>'.$edge['no']."</td>";
      $html[] = '    <td>'.$edge['slabel']."</td>";
      $html[] = '    <td>'.$edge['tlabel']."</td>";
      $html[] = '    <td align="right">'.$edge['confs']."</td>";
      $html[] = '    <td align="right">'.ceil($edge['c']/60)." l.</td>";
      $html[] = '    <td align="right">'.$edge['sp']."</td>";
      $html[] = '    <td align="right">'.number_format($edge['c']/($edge['sp']*60), 2, ',', ' ')." l.</td>";
      $html[] = "  </tr>";
    }

    $html[] = '</table>';
    return implode("\n", $html);
  }
  /**
   * Table des rôles
   */
  public function roletable ($playcode) {
    $play = $this->pdo->query("SELECT * FROM play where code = ".$this->pdo->quote($playcode))->fetch();
    $html = array();
    $html[] = '
<table class="sortable">
  <tr>
    <th title="Nom du personnage dans l’ordre de la distribution.">Personnage</th>
    <th title="Nombre de rôles interagissant avec le personnage.">Interl.</th>
    <th title="Part du texte de la pièce (en signes) où le personnage est présent.">Présence</th>
    <th>Entrées</th>
    <th title="Part du texte de la pièce, prononcée par le personnage (en signes).">Paroles</th>
    <th title="Part du texte que le personnage prononce, durant son temps de présence (en signes).">Par. % prés.</th>
    <th title="Nombre de répliques du personnages.">Répl.</th>
    <th title="Taille moyenne des répliques du personnage, en lignes (60 signes).">Répl. moy.</th>
  </tr>
  ';
    $html[] = '  <tr>';
    $html[] = '    <td data-sort="0">[TOUS]</td>';
    $html[] = '    <td align="right">'.number_format($play['presence']/$play['c'], 1, ',', ' ').'</td>';
    $html[] = '    <td align="right">100 %</td>';
    $html[] = '    <td align="right">'.number_format($play['entries']/$play['roles'], 1, ',', ' ').'</td>';
    $html[] = '    <td align="right">'.number_format($play['c']/60, 0, ',', ' ').' l.</td>';
    $html[] = '    <td align="right">'.ceil(100 * $play['c']/$play['presence'])." %</td>";
    $html[] = '    <td align="right">'.$play['sp'].'</td>';
    $html[] = '    <td align="right">'.number_format($play['c']/($play['sp']*60), 2, ',', ' ').' l.</td>';
    $html[] = '  </tr>';
    $i = 1;
    foreach ($this->pdo->query("SELECT * FROM role WHERE role.play = ".$play['id']." ORDER BY ord") as $role) {
      $html[] = "  <tr>";
      $html[] = '    <td data-sort="'.$i.'">'.$role['label']."</td>";
      $html[] = '    <td align="right">'.$role['targets']."</td>";
      $html[] = '    <td align="right">'.ceil(100 * $role['presence']/$play['c'])." %</td>";
      $html[] = '    <td align="right">'.$role['entries'].'</td>';
      $html[] = '    <td align="right">'.ceil(100 * $role['c']/$play['c'])." %</td>";
      $html[] = '    <td align="right">'.ceil( 100 * $role['c']/$role['presence'])." %</td>";
      $html[] = '    <td align="right">'.$role['sp']."</td>";
      if ($role['sp']) $html[] = '    <td align="right">'.number_format($role['c']/($role['sp']*60), 2, ',', ' ')." l.</td>";
      else $html[] = '<td align="right">0</td>';
      // echo '    <td align="right">'.$node['ic']."</td>\n";
      // echo '    <td align="right">'.$node['isp']."</td>\n";
      // echo '    <td align="right">'.round($node['ic']/$node['isp'])."</td>\n";
      $html[] = "  </tr>";
      $i++;
    }
    $html[] = '</table>';
    return implode("\n", $html);
  }
  /**
   * Liste de nœuds, pour le graphe, on filtre selon le type d'acte
   */
  public function nodes($playcode, $acttype=null) {
    $play = $this->pdo->query("SELECT * FROM play where code = ".$this->pdo->quote($playcode))->fetch();
    $data = array();
    $rank = 1;

    $qact = $this->pdo->prepare("SELECT act.* FROM presence, configuration, act WHERE act.type = ? AND presence.role = ? AND presence.configuration = configuration.id AND configuration.act = act.id ");
    foreach ($this->pdo->query("SELECT * FROM role WHERE role.play = ".$play['id']." ORDER BY role.c DESC") as $role) {
      // role invisible dans les configurations
      if (!$role['sources']) continue;
      if ($acttype) {
        $qact->execute(array($acttype, $role['id']));
        if (!$qact->fetch()) continue;
      }
      $class = "";
      if ($role['sex'] == 2) $class = "female";
      else if ($role['sex'] == 1) $class = "male";
      if ($role['status'] == 'exterior') $class .= " exterior";
      else if ($role['status'] == 'inferior') $class .= " inferior";
      else if ($role['status'] == 'superior') $class .= " superior";
      else if ($role['age'] == 'junior') $class .= " junior";
      else if ($role['age'] == 'veteran') $class .= " veteran";
      $role['class'] = $class;
      $role['rank'] = $rank;
      $data[$role['code']] = $role;
      $rank++;
    }
    return $data;
  }
  /**
   * Relations paroles entre les rôles
   */
  public function edges($playcode, $acttype = null) {
    $play = $this->pdo->query("SELECT * FROM play where code = ".$this->pdo->quote($playcode))->fetch();
    // load a dic of rowid=>code for roles
    $cast = array();
    foreach  ($this->pdo->query("SELECT id, code, label, c FROM role WHERE play = ".$play['id'], PDO::FETCH_ASSOC) as $row) {
      $cast[$row['id']] = $row;
    }
    $sql = "SELECT
      edge.source,
      edge.target,
      count(sp) AS sp,
      sum(sp.c) AS c,
      count(DISTINCT configuration) AS confs,
      (SELECT c FROM role WHERE edge.source=role.id)+(SELECT c FROM role WHERE edge.target=role.id) AS sort
    FROM edge, sp
    WHERE edge.play = ? AND edge.sp = sp.id
    GROUP BY edge.source, edge.target
    ORDER BY sort DESC
    ";
    $q = $this->pdo->prepare($sql);

    $q->execute(array($play['id']));
    $data = array();
    $max = false;
    $nodes = array();
    $i = 1; // more
    while ($row = $q->fetch()) {
      if(!isset($cast[$row['source']])) continue; // sortie de la liste des rôles
      if(!isset($cast[$row['target']])) continue; // sortie de la liste des rôles

      if(!$max) $max = $row['c'];
      /*
      $dothreshold = false; // no threshold
      if ($sp['source']==$sp['target']);
      else if (!isset($nodes[$sp['source']])) {
        $nodes[$sp['source']] = 1;
        $dothreshold = false;
      }
      else {
        $nodes[$sp['source']]++;
      }

      if ($sp['source']==$sp['target']);
      else if (!isset($nodes[$sp['target']])) {
        $nodes[$sp['target']] = 1;
        $dothreshold = false;
      }
      else {
        $nodes[$sp['target']]++;
      }
      // a threshold, to apply only on relation already linked to the net
      if ($dothreshold && ( $sp['ord'] <100 || ($sp['ord']/$play['c']) < 0.01) ) {
        continue;
      }
      */
      $i++;
      $data[] = array(
        'no' => $i,
        'sort' => 0+$row['sort'],
        'source' => $cast[$row['source']]['code'],
        'slabel' => $cast[$row['source']]['label'],
        'target' => $cast[$row['target']]['code'],
        'tlabel' => $cast[$row['target']]['label'],
        'c' => $row['c'],
        'sp' => $row['sp'],
        'confs' => $row['confs'],
      );
    }
    return $data;
  }


}

 ?>
