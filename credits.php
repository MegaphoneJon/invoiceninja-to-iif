#!/usr/bin/php
<?php

use League\Csv\Reader;

require __DIR__ . '/vendor/autoload.php';

$inputFile = 'input/credits.csv';
$outputFile = 'output/credits.iif';

// Get the CSV data.
$csv = Reader::createFromPath($inputFile, 'r');
$csv->setHeaderOffset(0);
// Create an output file.
$iif = fopen($outputFile, 'w');

writePaymentHeaders($iif);

$query = \League\Csv\Statement::create();
$records = $query->process($csv);
foreach ($records as $record) {
  if ($record['Client']) {
    writePayment($iif, $record);
  }
  else {
    break;
  }
}
fclose($iif);

function writePaymentHeaders($iif) : void {
  $headers = '!TRNS	TRNSTYPE	DATE	NAME	AMOUNT' . "\n";
  $headers .= '!SPL	TRNSTYPE	DATE	NAME	AMOUNT' . "\n";
  $headers .= '!ENDTRNS			' . "\n";
  fwrite($iif, $headers);
}

function writePayment($iif, array $record) : void {
  // Seems like an invoice paid by credit and otherwise shows twice, once without an amount. Let's skip.
  if (!$record['Amount']) {
    return;
  }
  $date = (new DateTime($record['Date']))->format('n/j/Y');
  $client = $record['Client'];
  $amount = preg_replace('/[^0-9.]/s', '', $record['Amount']);
  $trns = "TRNS	PAYMENT	$date	$client	$amount\n";
  $spl = "SPL	PAYMENT	$date		-$amount\n";
  $endtrns = "ENDTRNS			\n";
  fwrite($iif, $trns);
  fwrite($iif, $spl);
  fwrite($iif, $endtrns);
}
