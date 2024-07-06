#!/usr/bin/php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

require __DIR__ . '/vendor/autoload.php';
$inputFile = 'input/payables.csv';
$outputFile = 'output/payables.iif';
$unknownOutputFile = 'output/unknownpayables.csv';

// Get the CSV data.
$csv = Reader::createFromPath($inputFile, 'r');
$csv->setHeaderOffset(0);

// Create an output file.
$iif = fopen($outputFile, 'w');
writeHeaders($iif);

// Create a CSV file for unknown payables.
$unknownCsv = Writer::createFromPath($unknownOutputFile, 'w');

// Main loop.
$query = \League\Csv\Statement::create();
$payables = $query->process($csv);
foreach ($payables as $payable) {
  writePayable($iif, $unknownCsv, $payable);
}
fclose($iif);

function writeHeaders($iif) : void {
  $headers = '!TRNS	TRNSTYPE	DATE	ACCNT	NAME	AMOUNT	DOCNUM	MEMO	TOPRINT' . "\n";
  $headers .= '!SPL	TRNSTYPE	DATE	ACCNT	NAME	AMOUNT	DOCNUM	MEMO' . "\n";
  $headers .= '!ENDTRNS						' . "\n";
  fwrite($iif, $headers);
}

function writePayable($iif, Writer $unknownCsv, array $payable) {
  // If this isn't a debit, ignore.
  if (!$payable['Debit']) {
    return;
  }
  // Gusto payments can't be determined from bank statements - ignore.
  if (str_starts_with($payable['Text'], 'GUSTO/TAX')) {
    return;
  }
  if (str_starts_with($payable['Text'], 'GUSTO/NET')) {
    return;
  }
  // Ignore wire transfer fees, they're handled already.
  if (str_starts_with($payable['Text'], 'SERVICE CHARGE FOR WIRE TRANSFER')) {
    return;
  }

  // OK, now down to business.
  $date = (new DateTime($payable['Post Date']))->format('n/j/Y');
  [$vendor, $account, $lineItemMemo] = determineVendorAndAccount($payable['Text']);
  $amount = $payable['Debit'];
  $checkMemo = $payable['Text'];
  $lineItemMemo ??= $checkMemo;
  $docNumber = determineDocNumber($payable['Text'], $payable['Additional Reference']);
  if ($vendor === 'Unknown') {
    $unknownCsv->insertOne($payable);
    return;
  }
  $trns = "TRNS	CHECK	$date	Amalgamated	$vendor	-$amount	$docNumber	$checkMemo	N\n";
  $spl = "SPL	CHECK	$date	$account		$amount		$lineItemMemo\n";
  $endtrns = "ENDTRNS							\n";
  fwrite($iif, $trns);
  fwrite($iif, $spl);
  fwrite($iif, $endtrns);
}

function determineDocNumber(string $text, ?string $checkNumber): string {
  if ($text === 'Check') {
    return $checkNumber;
  }
  elseif (str_starts_with($text, 'POS Purchase')) {
    return 'DB';
  }
  return 'EFT';
}

function determineVendorAndAccount(string $text): array {
  // Donations to clients.
  if (str_contains($text, 'ARMENIAN GENERAL BENEV')) {
    return ['Armenian General Benevolent Union', 'Charitable Contributions', NULL];
  }
  if (str_contains($text, 'URGENT ACT*')) {
    return ['Urgent Action Fund for Feminist Activism', 'Charitable Contributions', NULL];
  }
  if (str_contains($text, 'SOUTHWEST ENVIRO/WILDMESQUI')) {
    return ['Southwest Environmental Center', 'Charitable Contributions', NULL];
  }
  if (str_contains($text, 'SW ENVIRONMENTAL CENTE')) {
    return ['Southwest Environmental Center', 'Charitable Contributions', NULL];
  }

  // Personal expenses
  
  if (str_contains($text, 'MSPBNA BANK/TRANSFER')) {
    return ['Jonathan Goldberg', 'Owner’s Equity', 'Owner\s Draw - Transfer to E*Trade/Morgan Stanley'];
  }
  if (str_contains($text, 'PFS MSBI')) {
    return ['Jonathan Goldberg', 'Owner’s Equity', 'Mount Sinai - personal expense'];
  }
  if (str_contains($text, 'MTA*LIRR ETIX TICKET')) {
    return ['Jonathan Goldberg', 'Owner’s Equity', 'LIRR ticket - personal expense'];
  }
  if (str_contains($text, 'IRVING PHARMACY')) {
    return ['Jonathan Goldberg', 'Owner’s Equity', 'Irving Pharmacy - personal expense'];
  }
  if (str_contains($text, 'NEIGHBORHOOD DENTAL')) {
    return ['Jonathan Goldberg', 'Owner’s Equity', 'Dentist visit - personal expense'];
  }
  if (str_contains($text, 'TRADER JOE')) {
    return ['Jonathan Goldberg', 'Owner’s Equity', 'Trader Joe\'s - personal expense'];
  }
  // Places I frequently eat while on business
  if (str_contains($text, 'GOLDEN LOTUS')) {
    return ['Golden Lotus', 'Meals and Entertainment', NULL];
  }
  // Frequently recurring expenses.
  if (str_starts_with($text, 'THE HARTFORD')) {
    return ['Hartford Insurance', 'Insurance Expense', NULL];
  }
  if (str_contains($text, 'LINODE')) {
    return ['Linode', 'Computer and Internet Expenses', NULL];
  }
  if (str_contains($text, 'MAY FIRST')) {
    return ['May First', 'Computer and Internet Expenses', NULL];
  }
  if (str_contains($text, 'MetroPlus/HealthPlan')) {
    return ['Metroplus Health', 'Health Insurance', NULL];
  }
  if (str_contains($text, 'NYS DOS CORP')) {
    return ['NYS Department of State', 'Business Licenses and Permits', NULL];
  }
  if (str_starts_with($text, 'Maryland Interac/MD GovPay')) {
    return ['Maryland Department of State', 'Business Licenses and Permits', NULL];
  }
  if (str_starts_with($text, 'RESEAU KOUMBIT')) {
    return ['Koumbit', 'Web Hosting Resale', NULL];
  }
  if (str_contains($text, 'NEWEGG')) {
    return ['Newegg', 'Computer and Internet Expenses', NULL];
  }
  if (str_contains($text, 'RSYNC NET')) {
    return ['rsync.net', 'Computer and Internet Expenses', NULL];
  }
  if (str_contains($text, 'AMERICAN FORT WORTH')) {
    return ['American Airlines', 'Travel Expense', NULL];
  }
  if (str_contains($text, 'CLIPPER SYSTEMS MOBI')) {
    return ['BART SF/Clipper Systems', 'Travel Expense', NULL];
  }
  if (str_contains($text, 'MTA*MNR ETIX TICKET')) {
    return ['MTA NYC', 'Travel Expense', NULL];
  }
  if (str_contains($text, 'MZLA THUNDERBIRD')) {
    return ['Mozilla', 'Charitable Contributions', NULL];
  }
  
  return ['Unknown', 'Unknown', NULL];
}
