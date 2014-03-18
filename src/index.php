<?php

namespace rsanderson\CQLParser;

require "cql.php";
?>
<!DOCTYPE html>
<!--
  Title: CQL-PHP Version 0.8 Testpage
  Author:  Omar Siam
  Date:  2014-08-13
  Copyright: ACDH ÖAW
  Licence: GPL
-->
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
    </head>
    <body>
        <?php

        function &parse($query) {
            $p = new CQLParser($query);
            $o = $p->query();
            if ($p->get_current_token() && get_class($o) != "diagnostic") {
                return new Diagnostic("Unprocessed tokens in query");
            } else {
                return $o;
            }
        }

        $q = "(dc.a foo.b/1/2=3 c and/4>5 cql.d e f) or rec.g h i sortBy X1/b2 X2";
        $o = &parse($q);

        echo "<hr>";
        echo htmlentities($q);
        echo "<br>";

        $cn = get_class($o);
        if ($cn == "diagnostic") {
            echo "<pre>";
            print htmlentities($o->toXML());
            echo "</pre>";
        } else {
            $c = new CQLConfig();
            $o->config = $c;
            echo "<pre>";
            echo htmlentities($o->toCQL());
            echo "\n\n";
            echo htmlentities($o->toTxt());
            echo "\n\n";
            echo htmlentities($o->toXCQL());
            echo "</pre>";
        }



        /*
          $data = file_get_contents("sampleQueries.txt");
          $lines = split("\n", $data);

          foreach ($lines as $q) {
          $p = new CQLParser($q);
          $o = $p->query();
          if ($o) {
          echo "<hr>";
          echo htmlentities($q);
          echo "<br>";
          echo "<pre>";
          echo htmlentities($o->toXCQL(0));
          echo "</pre>";
          }
          }

         */
        ?>
    </body>
</html>
