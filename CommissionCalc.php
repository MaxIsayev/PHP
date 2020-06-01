<?php
//$argv[1] - kelias į failą (1 programos argumentas po programos pavadinimo);
$operations = array ();
$file_handle = fopen($argv[1],"r");

$i = 0;
while (!feof($file_handle) ) {
    $line_of_text = fgets($file_handle);
    $parts = explode(',', $line_of_text);
    $parts[6]= date ( 'N', strtotime($parts[0]) );//savaitės diena

    $date=date_create($parts[0]);
    $parts[7]=$i;//nuskaitomosois eilutės pradinis numeris
    $parts[8]= date_sub($date,date_interval_create_from_date_string(($parts[6]-1)." days"));//praeiti pirmadieniai
    $operations[] =$parts;
    $i=$i+1;
}
//rūšiavimas
$columns1  = array_column($operations, 3);//pagal operacijos tipą
$columns2  = array_column($operations, 1);//pagal vartotojo numerį
$columns3  = array_column($operations, 8);//pagal praeitą pirmadienį
$columns4  = array_column($operations, 0);//pagal operacijos datą
$columns5  = array_column($operations, 7);//pagal faile nuskaitomosois eilutės pradinį numerį
array_multisort($columns1, SORT_ASC, $columns2, SORT_ASC, $columns3, SORT_ASC, $columns4, SORT_ASC, $columns5, SORT_ASC, $operations );
//konvertavimas į euro skaičiavimams
for ($j = 0; $j < $i; $j++) {
    if (trim($operations[$j][5])=="JPY"){
        $operations[$j][4]=$operations[$j][4]/129.53;
    }elseif (trim($operations[$j][5])=="USD"){
        $operations[$j][4]=$operations[$j][4]/1.1497;
    }
}

for ($j = 0; $j < $i; $j++) {
    if ($operations[$j][3]=="cash_out"){
        break;
    }else{
        $operations[$j][9]=0;//cash_out operacijų skaitiklis per savaitę
        $operations[$j][10]=0;//pirmųjų 3 operacijų bendra suma
        $operations[$j][11]=0;//viršijimas (kiek daugiau už 1000)
        $operations[$j][12]=$operations[$j][4]*0.0003;//komisiniai
        if ($operations[$j][12]>5){
            $operations[$j][12]=5;
        }
    }
}
$first_cash_out=$j;
//toliau tik cash_out operacijos
if ($operations[$first_cash_out][2]=="natural"){
        $operationcounter=1;
        $totalamount=$operations[$first_cash_out][4];
        $exceedamount=$totalamount-1000;
        if ($exceedamount<1000){
            $exceedamount=0;
        }
} else {
    $operationcounter=0;
    $totalamount=0;
    $exceedamount=0;
}
$operations[$first_cash_out][9]=$operationcounter;//cash_out operacijų skaitiklis per savaitę
$operations[$first_cash_out][10]=$totalamount;//pirmųjų 3 operacijų bendra suma
$operations[$first_cash_out][11]=$exceedamount;//kiek daugiau už 1000
if ($operations[$first_cash_out][2]=="legal") {
    //commission
    $operations[$first_cash_out][12] =$operations[$first_cash_out][4]*0.003;
    if ($operations[$first_cash_out][12]<0.5){
        $operations[$first_cash_out][12]=0.5;
    }
}else{
    $operations[$first_cash_out][12] =$operations[$first_cash_out][11]*0.003;
}

for ($j = $first_cash_out+1; $j < $i; $j++) {

    if ($operations[$j][2]=="legal") {
        $operationcounter=0;
        $totalamount=0;
        $exceedamount=0;
        $operations[$j][9] = $operationcounter;
        $operations[$j][10] = $totalamount;
        $operations[$j][11] = $exceedamount;
        //commission
        $operations[$j][12] =$operations[$j][4]*0.003;
        if ($operations[$j][12]<0.5){
            $operations[$j][12]=0.5;
        }
    }else{//raktas (vartotojų numeris ir pirmadienio data) kartojasi
        if (($operations[$j][8] == $operations[$j - 1][8]) and ($operations[$j][1] == $operations[$j - 1][1]))
        {
            $operationcounter = 1 + $operationcounter;
            if ($operationcounter <= 3) {
                $totalamount = $totalamount + $operations[$j][4];
                if ($totalamount < 1000) {
                    $exceedamount = 0;
                    $operations[$j][12] = 0;
                } else {//bendri komisiniai per savaitę
                    $exceedamount = $totalamount - 1000;
                    $operations[$j][12] = $exceedamount*0.003;
                }
                //komisiniai vienai operacijai (išvedant)
                if($operationcounter==2){
                    $operations[$j][12] = $operations[$j][12]-$operations[$j-1][12];
                }elseif($operationcounter==3){
                    $operations[$j][12] = $operations[$j][12]-$operations[$j-1][12]-$operations[$j-2][12];
                }
            } else {
                $totalamount = 0;
                $exceedamount = 0;
                $operations[$j][12] =  $operations[$j][4]*0.003;
            }
            $operations[$j][9] = $operationcounter;
            $operations[$j][10] = $totalamount;
            $operations[$j][11] = $exceedamount;
        } else {//raktas (vartotojų numeris ir pirmadienio data) nesikartoja
            $operationcounter = 1;
            $totalamount = $operations[$j][4];
            if ($totalamount < 1000) {
                $exceedamount = 0;
                $operations[$j][12] = 0;
            } else {
                $exceedamount = $totalamount - 1000;
                $operations[$j][12] = $exceedamount*0.003;
            }
            $operations[$j][9] = $operationcounter;
            $operations[$j][10] = $totalamount;
            $operations[$j][11] = $exceedamount;
        }
    }
}

//komisinių konvertavimas į pradinę valiutą ir apvalinimas
for ($j = 0; $j < $i; $j++) {
    if (trim($operations[$j][5])=="JPY"){
        $operations[$j][12]=ceil($operations[$j][12]*129.53);

    }elseif (trim($operations[$j][5])=="USD"){
        $operations[$j][12]=round($operations[$j][12]*1.1497, 2);

    }else{
        $operations[$j][12]=round($operations[$j][12], 2);
    }
}
//atstatoma pradinė tvarka (kaip faile)
$columns = array_column($operations, 7);
array_multisort($columns, SORT_ASC, $operations);

fclose($file_handle);

for ($j = 0; $j < $i; $j++) {
    echo number_format($operations[$j][12], 2,'.', '')."\n";
}
?>