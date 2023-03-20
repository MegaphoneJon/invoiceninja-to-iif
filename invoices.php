#!/usr/bin/php
<?php

use League\Csv\Reader;

require __DIR__ . '/vendor/autoload.php';

$inputFile = 'input/invoices.csv';
$outputFile = 'output/invoices.iif';

// Get the CSV data.
$csv = Reader::createFromPath($inputFile, 'r');
$csv->setHeaderOffset(0);
// Create an output file.
$iif = fopen($outputFile, 'w');

writeHeaders($iif);

$query = \League\Csv\Statement::create();
$invoices = $query->process($csv);
foreach ($invoices as $invoice) {
  if ($invoice['Invoice Date']) {
    writeInvoice($iif, $invoice);
  }
  else {
    break;
  }
}
fclose($iif);

function writeHeaders($iif) : void {
  $headers = '!TRNS	TRNSID	TRNSTYPE	DATE	ACCNT	NAME	CLASS	AMOUNT	DOCNUM		' . "\n";
  $headers .= '!SPL	SPLID	TRNSTYPE	DATE	ACCNT	NAME	CLASS	AMOUNT	DOCNUM	PRICE	INVITEM' . "\n";
  $headers .= '!ENDTRNS										' . "\n";
  fwrite($iif, $headers);
}

function writeInvoice($iif, array $invoice) {
  // Seems like an invoice paid by credit and otherwise shows twice, once without an amount. Let's skip.
  if (!$invoice['Amount']) {
    return;
  }
  $date = (new DateTime($invoice['Invoice Date']))->format('n/j/Y');
  $client = $invoice['Client'];
  $amount = preg_replace('/[^0-9.]/s', '', $invoice['Amount']);
  $invoiceNumber = filter_var($invoice['Invoice Number'], FILTER_SANITIZE_NUMBER_INT);
  $item = str_starts_with($invoice['Invoice Number'], 'R') ? 'Maintenance Plan' : 'IT Consulting';
  $trns = "TRNS		INVOICE	$date	Accounts Receivable	$client		$amount	$invoiceNumber\n";
  $spl = "SPL		INVOICE	$date	Consulting Income			-$amount		$amount	$item\n";
  $endtrns = "ENDTRNS										\n";
  fwrite($iif, $trns);
  fwrite($iif, $spl);
  fwrite($iif, $endtrns);
}
