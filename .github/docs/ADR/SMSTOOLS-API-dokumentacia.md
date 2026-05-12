
# O2 SMS Connector API 3.5

Službu zabezpečuje COM-TRADE s.r.o. pre
O2 Slovakia, s.r.o. a O2 Business Services, a.s.

## 2. Úvod

Programové rozhranie (API) je postavené na HTTPS protokole a pre výmenu štrukturovaných
dát používa JSON formát.
### 2.1. Použité pojmy

• Správa - jedna sms odoslaná na jedno konkrétne telefónne číslo - každá správa patrí práve
jednej dávke
• Dávka - správy s rovnakým textom odoslané na jedno a viac telefónnych čisiel - dávka
obsahuje jednotlivé správy
• API - aplikačné programové rozhranie
• JSON - dátová štruktúra zapísaná v textovej - čitatelnej forme - viac info https://
en.wikipedia.org/wiki/JSON
• PERL - programovací jazyk - viac info https://en.wikipedia.org/wiki/Perl
• HTTPS - alebo tiež HTTP cez TLS - protokol HTTP cez pripojenie kryptované protokolom
TLS - všetky tečúce dáta sú "neviditelné" na ceste medzi klientom a serverom
• TLS protokol - Transport Layer Security - https://en.wikipedia.org/wiki/
Transport_Layer_Security
• OpenSSL - nástroj pre TLS - https://www.openssl.org
• GSM 3.38 - znaková sada - GSM 3.38

### 2.2. Bezpečnosť

Všetky API volania využívajú protokol HTTPS (TLS zabezpečuje nástroj OpenSSL), ktorý je
vzhľadom na účel použitia vyhodnotený našou spoločnosťou ako dostatočne bezpečný.
Vaše dáta obsiahnuté v požiadavke ako aj v prijatej odpovedi sú zakryptované (medzi sms
serverom a vašim systémom).
Podporujeme uvedené šifrovacie protokoly
• TLS 1.2, TLS 1.3

### 2.3. Limity

V popise jednotlivých systémov sú uvedené obmedzenia alebo limity ako napríklad maximálny
počet súbežných pripojení na API z vašeho systému.


## 3. Popis systému

### 3.1. API

** API URL:
• https://api.smstools.sk

** Zápis kompletného API URL (aj s verziou api a volanou funkciou):
• Všeobecne: https://api.smstools.sk/3/"názov funkcie"
• Príklad: https://api.smstools.sk/3/send_batch

** Limity:
• max. 100 súbežných volaní API z vašeho prostredia
Volania prebiehajú prostredníctvom HTTP POST alebo HTTP GET (pokiaľ nevyužívate
pokročilé funkcie API)
Vstupné aj výstupné dáta sú zapísané v JSON formáte.
Upozornenie: v príkladoch uvádzame komentáre v JSON štruktúrach, ktoré nie sú v JSON
platné (je potrebné ich odstrániť, ak budete JSON štruktúry kopírovať do vašich programov).
Kontaktujte nás, ak potrebujete pomôcť pri implementácii: podpora@smstools.sk

** Všeobecné návratové hodnoty

Hodnoty spoločné pre všetky API funkcie (ostatné návratové hodnoty sú definované v
dokumentácii pri jednotlivých API funkciách).
|	ID	 							|	POPIS															|
|-----------------------------------|-------------------------------------------------------------------|
|	OK 								|	Operácia prebehla vporiadku										|
|	NESPRAVNE_MENO_HESLO 			|	Chybné meno alebo heslo											|
|	NESPRAVNE_MENO_HESLO_BLOKACIA 	|	Chybné meno alebo heslo - blokácia na 15 min.					|
|	NEDEFINOVANA_FUNKCIA 			|	Funkcia ( "nazov_funkcie" ) neexistuje. 						|
|									|	Volaná cez https://api.smstools.sk/3/nazov_funkcie				|
|	NOT_PERMITTED_RESELLER_CUST 	|	Autentifikovaný reseller chce vykonať operáciu nad 				|
|									|	zákazníkom, ktorému nie je reseller. Operácia je zamietnutá		|
|	MAINT_AUTH_REJECTED 			|	Prebieha údržba systému - služba je nedostupná					|
|	INA_CHYBA 						|	Bližšie info o chybe nájdete v elemente "note" - 				|
|									|	napr. "Skontrolujte JSON syntax alebo URL"						|
|	API_DISABLED 					|	API je pre zákazníka vypnuté									|
	
### 3.1.1. Príklady volania API

Uvádzame príklady volania API v jazyku JavaScript, PERL, PHP a C++. Kód je použiteľný pre
všetky API funkcie - mení sa len URL, JSON vstupné a výstupné dáta.

** Príklad odoslania SMS v jazyku JavaScript (Node.js® runtime)

```json
const https = require('https')
const data = JSON.stringify(
{
auth: {
apikey: 'VÁŠ-APIKEY'
},
data: {
message: 'TEST',
sender: {
text:'TEST'
},
recipients: [
{
phonenr:'+421905000000',
phonenr:'+421905000001'
}
]
}
})
const options = {
hostname: 'api.smstools.sk',
port: 443,
path: '/3/send_batch',
method: 'POST',
headers: {
'Content-Type': 'application/json;charset=UTF-8',
'Content-Length': data.length
}
}
const req = https.request(options, res => {
console.log(`statusCode: ${res.statusCode}`)
res.on('data', d => {
process.stdout.write(d)
console.error(data)
})
})
req.on('error', error => {
console.error(error)
})
req.write(data)
req.end()
```

** Príklad odoslania SMS v jazyku PERL

```perl
#!/usr/bin/perl
use LWP::UserAgent;
use JSON;
use JSON::Parse 'parse_json';
use Data::Dumper::AutoEncode;
my $ua = LWP::UserAgent->new;
my $server_endpoint = "https://api.smstools.sk/3/send_batch";
my $req = HTTP::Request->new(POST : $server_endpoint);
$req->header('Content-Type' => 'application/json;charset=UTF-8');
my %post_data = (
auth => { apikey => 'VÁŠ-APIKEY'},
data => {
message => "TEST",
sender => { text => 'TEST' },
recipients => [ { phonenr => '+421905000000'},
{ phonenr => '+421905000001'}
]
}
);
print "Odosielame [JSON]:\n\n" . eDumper (\%post_data);
# konverzia na JSON string
my $post_data = encode_json \%post_data;
# vykonáme HTTP POST
$req->content($post_data);
my $resp = $ua->request($req);
# úspešné volanie HTTP POST
if ($resp->is_success) {
my $message = parse_json( $resp->decoded_content);
print "Prijali sme [JSON]:\n\n" . eDumper ($message);
}
else {
print "HTTP POST error code: ", $resp->code, "\n";
print "HTTP POST error message: ", $resp->message, "\n";
}
```
** Príklad odoslania SMS v jazyku PHP
```php
<?php
$server_endpoint = "https://api.smstools.sk/3/send_batch";
$data = [
"auth" => [
"apikey" => "VÁŠ-APIKEY"
],
"data" => [
"message" => 'TEST',
"sender" => [ "text" => 'TEST' ],
"recipients" => [
[ "phonenr" => '+421905000000' ],
[ "phonenr" => '+421905000001' ]
]
]
];
$content = json_encode($data);
$curl = curl_init($server_endpoint);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER,
array("Content-type: application/json;charset=UTF-8"));
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
$json_response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
if ($status != 201 && $status != 200) {
echo ("Error: call failed with status: " . $status .
", response: " . $json_response .
", curl_error: " . curl_error($curl) .
", curl_errno: " . curl_errno($curl)
);
} else {
$ret = json_decode($json_response, true);
print_r($ret);
}
?>
```

** Príklad odoslania SMS v jazyku C++
Vyžaduje:
1. curl knižnicu - https://curl.haxx.se/libcurl
2. json triedu - https://github.com/nlohmann/json
```c++
/* <DESC>
* Odoslanie SMS dávky
* </DESC>
*/
#include <stdio.h>
#include <curl/curl.h>
#include <iostream>
#include "json.hpp"
using json = nlohmann::json;
int main(void)
{
CURL *curl;
CURLcode res;
json data;
/* Zadáme dáta */
//API-KEY
data["auth"]["apikey"] = "VÁŠ-APIKEY";
/* Správa */
data["data"]["message"] = "TEST";
/* Odosielateľ */
data["data"]["sender"]["text"] = "TEST";
/* Adresát / adresáti */
data["data"]["recipients"][0]["phonenr"] = "+421905000000" ;
data["data"]["recipients"][1]["phonenr"] = "+421905000001" ;
/* Na OS Windows inicializuje zalezitosti okolo winsock */
curl_global_init(CURL_GLOBAL_ALL);
/* Handle */
curl = curl_easy_init();
if(curl) {
/* Nastavíme URL pre odosielanie dávky */
curl_easy_setopt(curl, CURLOPT_URL, "https://api.smstools.sk/3/
send_batch");
/* Nastavíme post dáta */
curl_easy_setopt(curl, CURLOPT_POSTFIELDSIZE, -1L);
curl_easy_setopt(curl, CURLOPT_COPYPOSTFIELDS, data.dump().c_str());
/* Vykonáme volanie */
res = curl_easy_perform(curl);
/* Skontrolujeme chyby */
if(res != CURLE_OK)
fprintf(stderr, "curl_easy_perform() failed: %s\n",
curl_easy_strerror(res));
/* Cleanup */
curl_easy_cleanup(curl);
}
curl_global_cleanup();
return 0;
}
```

## 3.2. Callback
Callback URL môžete nastaviť cez náš web portál (administrácia -> nastavenia) alebo
požiadajte emailom podpora@smstools.sk. Niektoré API volania (napr. send_batch) umožňujú
definovať callback priamo pre dané volanie. Využijete, ak napríklad potrebujete používať rôzne
callback pre jednotlivé SMS dávky.
Následne môžete príjmať rôzne informácie (stavy odoslania a doručenia sms, nízka hodnota
kreditu, ...) "push" spôsobom. Nemusíte volať funkcie na získanie uvedených údajov ale náš
systém ich odošle v čase, keď dáta vzniknú.
Príklad vašej URL: https://moja.domena.tld/callback.php

** Náš systém zavolá: HTTP POST https://moja.domena.tld/callback.php s použitím Authorization Basic a odošle JSON štruktúru s dátami.**

** Authorisation Basic:
Autorizácia na vašej strane nie je povinná, zabezpečí však to, že váš script čakajúci na naše
callback volanie zavoláme skutočne len my a autorizácia je zabezpečená na úrovni vašeho web
servra. Náš systém sa snaží autorizáciu vykonať vždy, pokiaľ na vašej strane nie je
podporovaná, autorizácia je ignorovaná a volanie prebehne vporiadku.

** 	Preddefinované autorizačné údaje:
Meno: smstools
Heslo: %Z;)zxJS6l5O:T_P
Autorizacné údaje je možné zmeniť na web portáli.
Aby Autorizácia fungovala, je potrebné na vašom web serveri vykonať príslušné nastavenia.
Viac info https://en.wikipedia.org/wiki/Basic_access_authentication
Z dôvodu, že je použitá Basic autorizácia, URL voláme cez protokol HTTPS.
Vzhľadom na to, že URL môže zdielať viacero zákazníkov (ak reseller vytvorí viacerých
zákazníkov), uvádzame v štruktúre aj ID zákazníka, pokiaľ nie je možné z ostatných údajov
určiť zákazníka.
Rôzne typy callback údajov môžu byť kombinované v jednom callbacku ako jedna JSON
štruktúra (napr. notifikácia o nízkom kredite a doručení SMS môže byť v jednom callback
volaní).

** Detailný popis rôznych callback-ov nájdete v časti Callback

#### 3.2.1. Príklady
Príklad callback volania (z nášho systému) [JSON]:
```json
{
// stav žiadosti o kredit
"credit_request": [
{ "cust_id" : 1234,
"state_id" : "STAV",
"vs" : "202020",
"paid" :123.50,
"bonus" : 13.50 }
],
// upozornenie na nízky kredit
"warning_low_credit": [
{ "cust_id": 13,
"credit_value": 7 }
],
// stav SMS - odoslanie / doručenie
"sms_state": [
{ "msg_id": 1234567,
"state_type": "ODOSIELANIE",
"state_id": "PREBIEHA_ODOSIELANIE",
"permanent": "FALSE",
"price": 0.033,
"sms_count": 2 }
]
}
```


## 4. API funkcie

### 4.1. Odosielanie SMS

** Pokyny k odosielaniu SMS
1. Správa môže obsahovať ľubovoľné znaky
2. Názov odosielateľa môže obsahovať len znaky bez diakritiky, nemôže obsahovať medzeru
ani niektoré rezervované slová (názov operátora a podobne), maximálna dĺžka je
obmedzená na 11 znakov
3. Maximálny počet znakov v SMS zavisí od použitých znakov v texte.
Ak použijete znaky definované v GSM 3.38, maximálny počet znakov je 160 resp. 70 (ak
použijete aspon jeden znak mimo GSM 3.38 definície).
4. Pokial pošlete správu dlhšiu, tá je rozdelená na viaceré SMS segmenty s hlavičkou (jedna
správa už obsahuje len maximálne 153 znakov (GSM3.38) resp. 67 znakov) vďaka ktorej
ich vie príjemcov telefón opäť spojiť do jednej dlhej správy
5. Celková maximálna dĺžka multisegmentovej SMS (tzv. dlhej) je 459 (GSM3.38) znakov resp.
201
6. Pomocou parametra "simple_text":true vyžiadate aby sa náš systém snažil vami zadaný text
upraviť tak aby zodpovedal znakovej sade GSM3.38. Východziu hodnotu parametra
"simple_text" viete definovať cez Web portál smstools.sk v Administrácia -> Nastavenia
7. **Viac o znakovej sade GSM 3.38 nájdete v prílohe
8. Telefónne číslo adresáta môže byť vo viacerých formátoch:
- s medzinárodným prefixom: napr. +421901000001
- bez medzinárodného prefixu: napr. 0901000002
- bez medzinárodného prefixu s chýbajúcou úvodnou 0: napr. 901000003
(systém bude predpokladať, že sa jedná o mobilné číslo SR)

#### 4.1.1. Odoslanie jednej SMS dávky
Odosielame jednu správu na jedno a viac telefónnych čísiel.
URL: https://api.smstools.sk/3/send_batch

**Vstupné dáta [JSON]
```json
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC"},
//nepovinné (callback viete definovať aj cez web portál smstools.sk)
//použite ak chcete rôzny callback pre jednotlivé API volania
"callback" : {
"url" : "https://moja.domena/zber_dat",
"username" : "pouzivatel",
"passwd" : "heslo"
}
// sms dávka
"data" : {
// text sms
"message" : "SMS sprava",
// konverzia textu na jednoduchý (bez diakritiky)
// true - konverziu vykoná náš systém
// false - text nebude našim systémom upravovaný
"simple_text" : "false",
// odosielateľ sms
"sender" : {
// textový odosielateľ (max. 11 znakov)
"text" : "Firma AB",
// odosielateľ ako tel. /opýtajte sa nás pre viac info/
"phonenr" : "+421901000001", // nepovinné
// odosielateľ ako email /opýtajte sa nás pre viac info/
"email" : "mzahor@dtech.sk" // nepovinné
},
// príjemcovia sms (pozor, jedná sa o pole)
"recipients" : [
{ "phonenr" : "+421900000001"},
{ "phonenr" : "+421900000002"}
],
// stredisko (rozčlenenie nákladov a podobne)
"department" : "12345", //nepovinné
// načasovanie odoslania
"schedule" : "11:11 1.1.2011" //nepovinné
}
}
```

*** Výstupné dáta [JSON]
```json
{
// výsledok operácie (možné stavy sú popísané nižšie)
"id" : "OK",
"note" : "Popis výsledku operácie ak sa jedná o chybu",
// úspešný výsledok operácie vracia ID jednotlivých správ
"data" : {
// ID dávky - "batch_id"
"batch_id" : 12345,
"recipients" : {
// odmietnutí príjemcovia - nepoužíva sa
// akceptuje sa všetko, odmietnuté sms získate cez funkcie
// o zisťovaní stavu odoslania a doručenia
"rejected" : [{}],
// akceptovaní príjemcovia
"accepted" : [
{
// ID správy
"msg_id" : 22345,
"phonenr" : "+421900000001"
},
{
// ID správy
"msg_id" : 22346,
"phonenr" : "+421900000002"
}
]
},
// pole súvisiacich dávok
// (údaj sa používa len pri "extra dlhých" SMS, kde jedna SMS je
// zložená z viacerých súvisiacich dávok)
// atribút je definovaný len v prípade "extra dlhej" SMS
"linked_batch_id" : [
{
"batch_id": "12346",
"recipients": [
{
"msg_id": "22347",
"phonenr": "+421900000001"
},
{
"msg_id": "22348",
"phonenr": "+421900000002"
}
]
},
{
"batch_id": "12347",
"recipients": [
{
"msg_id": "22349",
"phonenr": "+421900000001"
},
{
"msg_id": "22350",
"phonenr": "+421900000002"
}
]
}
]
}
}
```

** Návratové hodnoty
Ostatné spoločné návratové hodnoty sú definované v časti 3.1

|	ID						 				|	POPIS
|-------------------------------------------|-----------------------------------------------------------------------------------------------|
|	SPRAVA_PRILIS_DLHA 						|	SMS správa je dlhšia ako 459 znakov															|
|	NEDOSTATOK_KREDITU 						Nedostatok kreditu na poslanie celej dávky														|
|	CHYBA_ODOSIELATEL_CISLO_ALEBO_TEXT_2 	Chýba čislo odosielateľa (len v prípade, že použivate virtuálne čislo u nás zriadené) alebo text identifikujúci odosielateľa	|
|	CHYBA_ODOSIELATEL_TEXT_2 				|	Chýba text identifikujúci odosielateľa														|
|	SPRAVA_NULOVA 							|	SMS správa má nulovú dlžku																	|
|	CHYBA_CISLO_ALEBO_FROMTEXT_0 			|	Chýba číslo odosielateľa alebo text identifikujúci odosielateľa [provider A].				|
|	CHYBA_EMAIL_2 							|	Chýba email odosielateľa [ provider B].														|
|	NEPOVOLENE_ZNAKY_TEXT 					|	V texte SMS sa nachádzajú nepovolené znaky.													|		
|	NEPOVOLENE_ZNAKY_OD 					|	V texte identifikujúcom ODOSIELATEĽA sa  nachádzajú nepovolené znaky.						|
|	DAVKA_BEZ_SPRAV 						|	Dávka neobsahuje žiadneho adresáta (prípadne ste zadali adresáta ktorého systém zamietol).	|
|	TEXT_OD_PRILIS_DLHY 					|	Text identifikujúci ODOSIELATEĽA je dlhší ako 11 znakov										|
|	SPRAVA_PRILIS_DLHA_EXTRA 				|	SMS správa je dlhšia ako 4000 znakov /povolené extra dlhé SMS/								|
|	TEXT_UNABLE_TO_CONVERT_GSM338 			|	Správa obsahuje znak, ktorý znemožňuje jej konverziu na jednoduchý text. Chyba nastane len ak ste vyžiadali konverziu na jednoduchý text.	|
|	APIKEY_SRC_IP_NOT_ALLOWED 				|	Volanie API prebehlo z IP adresy, ktorá nie je v zozname povolených adries.					|	

#### 4.1.2. Odoslanie viacerých SMS dávok

Odosielame rôzne správy na rôzne telefónne čísla.
URL: https://api.smstools.sk/3/send_batch_multi
Funkcia umožňuje odoslať v jednom API volani (v jednej JSON štruktúre) viacero SMS dávok
(rôzne SMS texty pre rôznych odosielateľov).
Doporučujeme využiť napr. v prípadoch odosielania personalizovaných SMS, kde každému
adresátovi je zaslaný jedinečný text, čiže pre každú jednu SMS je nutné zavolať API
samostatne pri použití funkcie send_batch
** Vstupné dáta [JSON]
```json
// JSON štruktúra je identická ako pri volaní send_batch s rozdielom,
// kde "data" predstavuje pole JSON štruktúr, na rozdiel od send_batch
"data" : [
{
... // JSON štruktúra, ako pri funkcii send_batch
},
{
... // JSON štruktúra, ako pri funkcii send_batch
}
]
```
** Výstupné dáta [JSON]
```javasript
// pole JSON štruktúr návratových hodnôt, ako pri funkcii send_batch
[
{
... // JSON návratová štruktúra, ako pri funkcii send_batch
},
{
... // JSON návratová štruktúra, ako pri funkcii send_batch
}
]
```
** Návratové hodnoty
Rovnaké ako pri funkcii "send_batch"

### 4.1.3. Zjednodušená forma odoslania (HTTP GET)

Umožnuje odoslať SMS prostredníctvom HTTP GET - teda definovať všetky parametre v URL -
vhodné napr. do existujúcich systémov, kde je možné definovať odosielanie SMS len ako
parametrizované URL ( alarmy, eshop-y a pod ).
Pozor, výsledné URL musí obsahovať správne enkódované URI parametre (to, from, text , ... ),
tzv. percent-encoding. Jedná sa o nahradenie rezervovaných znakov ( !, * , & a pod.) ich
enkódovanou reprezentáciou ( %21, %2A, %26 a pod.). Viac info: https://en.wikipedia.org/wiki/
Percent-encoding

** V programovacích jazykoch existujú funkcie schopné vykonať enkódovanie URI:
Perl: URI::Encode
PHP: urlencode
C++: knižnica libcurl, funkcia curl_easy_escape
Online pomôcka: https://www.url-encode-decode.com.
Špecifický je enkoding znaku "medzera", štandardne je enkódovaný ako %20, ak sa nachádza v
časti parametrov URL, teda po znaku "?", môže byť enkódovaný ako znak "+" namiesto %20.

** Príklad volania

Pred enkódovaním:

Dobry den,
dakujeme za Vas nakup.

Po enkódovaní:
Dobry%20den%2C%0Adakujeme%20za%20Vas%20nakup.

URL bude vyzerať:
https://api.smstools.sk/simple/api_key=VÁŠ-
APIKEY&from=TEST&to=421901000000&text=Dobry%20den%2C%0Adakujeme%20za%20Va
s%20nakup.

Samotné vykonanie príkazu, čiže použitie HTTP protokolu pre zavolanie daného URL môžete
implementovať programovo (podobne ako je uvedené v príkladoch) alebo pomocou programu
```
cURL (https://curl.haxx.se/download.html)
curl -i -H "Accept: application/json" -H "Content-Type: application/json" -X GET https://
api.smstools.sk/simple/api_key=VÁŠ-
APIKEY&from=TEST&to=421901000000&text=Dobry%20den%2C%0Adakujeme%20za%20Va
s%20nakup."
```
Návratová hodnota je v JSON formáte podľa popisu vyššie.

### 4.2. Zistenie stavu doručenia

#### 4.2.1. Stav doručenia jednej dávky

Stav odoslania resp. doručenia môžeme zisťovať buď po jednotlivých správach alebo za celú
dávku.
Pokiaľ definujeme len "msg_id", systém vráti stav len tejto jednej správy.
Ak definujeme "batch_id", systém vráti stavy všetkých správ v danej dávke.
Hodnoty "msg_id" resp. "batch_id" boli vrátené pri volaní funkcie "send_batch".
URL: https://api.smstools.sk/3/sms_get_state
** Vstupné dáta [JSON]
```javasript
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC" },
"data" : {
// ID dávky
"batch_id" : 12345 ,
// ID správy /ak je zadané batch_id, msg_id bude ignorované/
"msg_id" : 12345
}
}
```

** Výstupné dáta [JSON]
```javasript
{
// výsledok operácie (možné stavy sú popísané nižšie)
"id" : "OK",
"note" : "Popis výsledku operácie ak sa jedná o chybu",
"batch_id" : 12345,
// úspešný výsledok operácie vracia pole správ a ich stavy
"data" : [
{
"msg_id" : 12345,
// jednotková cena v EUR za SMS (bez DPH)
"price" => 0.035,
// počet sms na ktoré bola správa rozdelená
"sms_count" => 1,
"sending" : {
"id" : "ODOSLANA",
"note" : "Odoslaná"
// TRUE - stav je trvalý, nebude sa už meniť
// FALSE - stav sa môže zmeniť (napr. systém sa
// pri chybe pokúsi opakovať odoslanie SMS)
"permanent" : "TRUE"
},
"delivering" : {
"id" : "DORUCENA",
"note" : "Doručená"
"permanent" : "TRUE"
}
},
// hodnota data obsahuje pole stavov rôznych SMS, preto
// v jednom volaní môže byť vrátených viac stavov
{
"msg_id" : 12346,
"price" => 0.035,
"sms_count" => 1,
"sending" : {
"id" : "ODMIETNUTA",
"note" : "Odmietnuté odoslanie"
"permanent" : "TRUE"
},
"delivering" : {
"id" : "NEDEFINOVANY_STAV",
"note" : "Nedefinovaný stav"
"permanent" : "TRUE"
}
}
]
}
```

** Návratové hodnoty
Ostatné spoločné návratové hodnoty sú definované v časti 3.1

|	ID 					|	POPIS										|
|-----------------------|-----------------------------------------------|
|	SPRAVA_NEEXISTUJE 	|	Dopytovaná správa neexistuje				|
|	DAVKA_NEEXISTUJE	|	Dopytovaná dávka neexistuje					|
|	DAVKA_BEZ_SPRAV 	|	Dopytovaná dávka neobsahuje žiadne správy	|	

** Stavy správy

|	STAV ID 					|	POPIS																										|
|-------------------------------|---------------------------------------------------------------------------------------------------------------|
|	PREBIEHA_ODOSIELANIE 		|	Správa je v procese odosielania																				|
|	ODOSLANA 					|	Správa bola úspešne odoslaná																				|
|	POZASTAVENE 				|	Používateľ pozastavil odoslanie správy																		|
|	NEODOSLANA_FLOODING 		|	Správa bola opakovane odoslaná na to isté telefónne číslo v príliš krátkom intervale						|
|	NESPRAVNE_CISLO 			|	Nesprávne telefónne číslo - nesprávny formát alebo neplatné číslo											|
|	PREBIEHA_DORUCOVANIE 		|	Prebieha doručonie správy - expirácia nastane do 48 hodín (ak nie je určené inak) od pokusu doručiť správu	|
|	DORUCENA 					|	Správa bola doručená na cieľový telefón																		|
|	NEDORUCENA_EXPIROVANA 		|	Doručovanie expirovalo - do 48 hod. (ak nie je určené inak) nebolo možné správu doručiť na cieľový telefón	|
|	ODMIETNUTA 					|	Správa bola odmietnutá - kontaktujte našu tech. podporu														|
|	SYSTEMOVA_CHYBA 			|	Systémová chyba - kontaktujte našu tech. podporu															|
|	NEDEFINOVANY_STAV 			|	Stav nie je definovaný - kontaktujte našu tech. podporu														|
|	NEZNAME_DORUCENIE 			|	Nevieme posúdiť, či bola správa doručená																	|
|	CHYBA_DORUCENIA 			|	Iná chyba doručenia - kontaktujte našu tech. podporu														|

### 4.2.2. Stav doručenia viacerých dávok

Stav odoslania resp. doručenia môžeme zisťovať naraz (jedným volaním API) aj pre viacero
dávok.
URL: https://api.smstools.sk/3/sms_get_state_multi

** Vstupné dáta [JSON]
```javascript
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC" },
// pole definícii
"data" : [
{
... // JSON štruktúra, ako pri funkcii sms_get_state
},
{
... // JSON štruktúra, ako pri funkcii sms_get_state
}
]
}
```

** Výstupné dáta [JSON]
```json
// pole JSON návratových štruktúr, ako pri funkcii sms_get_state
[
{
... // JSON návratová štruktúra, ako pri funkcii sms_get_state
},
{
... // JSON návratová štruktúra, ako pri funkcii sms_get_state
}
]
```

**Návratové hodnoty
Rovnaké ako pre sms_get_state

## 4.3. Načítanie prijatých správ

Obojsmerná SMS komunikácia cez API (možnosť aj príjmať odpovede) je možná
prostredníctvom virtuálneho telef. čísla (VLN), v prípade záujmu o VLN nás kontaktujte.
URL: https://api.smstools.sk/3/sms_get_received

** Vstupné dáta [JSON]
```json
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC"},
"data" : {
// koľko správ chceme načítať
"data_limit" : 12345 // nepovinné (default 500)
}
}
```

** Výstupné dáta [JSON]

```json
{
// výsledok operácie (možné stavy sú popísané nižšie)
"id" : "OK",
"note" : "Popis výsledku operácie ak sa jedná o chybu",
// úspešný výsledok operácie vracia platobné údaje
"data" : {
"messages" : [{
// typ a obsah odpovede ( SMS / CALL )
"type" : "SMS",
// text prijatej SMS ( text "TELEFONAT" ak type = CALL )
"text" : "Text SMS",
// kedy bola sms / telefonat prijatý
"datetime": "16:09 21.03.2015",
// odosielateľ
"sender_phonenr": "421901000001",
// príjemca (len pri VLN)
"recipient_phonenr": "421901000002",
26
O2 SMS Connector
// ID pôvodnej správy (na ktorú bola odpoved zaslaná)
// (nie je k dispozícii pri VLN)
"msg_sent_id": 2916546,
// ID prijatej odpovede
"msg_received_id": 1128
}]
}
}
```

** Návratové hodnoty
Ostatné spoločné návratové hodnoty sú definované v časti 3.1

|	CHYBA ID		| 	POPIS						|
|-------------------|-------------------------------|				
ZIADNE_NOVE_SPRAVY 	|	Neexistujú ďalšie správy	|

## 4.4. Kredit

### 4.4.1. Žiadosť o kredit

Vytvorí požiadavku na nákup kreditu.
URL: https://api.smstools.sk/3/credit_request

** Vstupné dáta [JSON]
```json
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC"},
"data" : {
// kredit - value definuje hodnotu požiadavky o kredit v EUR
"credit" : { "value" : 20},
// ID klienta - ak chceme vykonat operaciu nad inym klientom,
// než sme sa autentifikovali ( môže len reseller )
"cust_id" : 123 //nepovinné
}
}
Výstupné dáta [JSON]
{
// výsledok operácie (možné stavy sú popísané nižšie)
"id" : "OK",
"note" : "Popis výsledku operácie ak sa jedná o chybu",
// úspešný výsledok operácie vracia platobné údaje
"data" : {
"vs" : "24242444",
// hodnota v EUR na zaplatenie
"value" : "50",
"iban": "SK072420000002423442",
// hodnota bonusu v EUR
"bonus": 0
}
}
```

** Návratové hodnoty

Ostatné spoločné návratové hodnoty sú definované v časti 3.1

|	ID 					|	POPIS														|
|-----------------------|---------------------------------------------------------------|
|CREDIT_REQUEST_TOO_LOW |	Hodnota žiadaného kreditu nemôze byť menšia ako minimum.	|
|						|	data:														|				
|						|	{															|	
|						|		// minimálna suma žiadosti o kredit						|
|						|		minimal_credit : 24,									|	
|						|		// mena													|
|						|		currency : 'EUR'										|
|						|	};															|

### 4.4.2. Zostávajúci kredit

Funkcia umožňuje získať informáciu o zostatku kreditu, a tiež o množstve SMS, ktoré je možné
odoslať v hodnote zostatkového kreditu. Pokiaľ chcete získať údaje o množstve zostávajúcich
SMS, je potrebné vo vstupnej JSON štruktúre uviesť parameter 'prefix' (prefix = telefonická
predvoľba krajiny).
Dopyt na výšku zostávajúceho kreditu:
URL: https://api.smstools.sk/3/credit_remaining

** Vstupné dáta [JSON]
```json
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC"},
"data" : {
// telefonická predvoľba krajiny
"prefix" : "421" //nepovinne
}
}
```

** Výstupné dáta [JSON]
```json
{
// výsledok operácie (možné stavy sú popísané nižšie)
"id" : "OK",
"note" : "Popis výsledku operácie ak sa jedná o chybu",
// úspešný výsledok operácie
"data" : {
"credit" : {
// zostávajúca hodnota kreditu
"amount" : 52.406000,
"currency" : "EUR"
},
"sms" : {
// zostávajúci počet SMS do krajiny určenej prefixom
"amount" : "1248",
"prefix" : "421"
}
}
}
```

** Návratové hodnoty
Ostatné spoločné návratové hodnoty sú definované v časti 3.1

|	ID	|	POPIS	|
|-------|-----------|
|		|			|

## 4.5. Zákazník

### 4.5.1. Žiadosť o registráciu

Volanie vytvorí požiadavku na registráciu nového zákazníka. Funkcia je prístupná len
zákazníkom so statusom Reseller. Po vytvorení žiadosti je nutné na našej strane registráciu
schváliť, pokiaľ nebude individuálne dohodnuté inak.
URL: https://api.smstools.sk/3/customer_register

** Vstupné dáta [JSON]
```json
{
// api key autentifikácia
"auth" : { "apikey" : "API-KEY-RETAZEC" },
"data" : {
// IČO a názov firmy podľa Obch. registra
// Údaje budú použité aj na fakturačné účely
"ico" : "2439756768",
"name" : "Firma XY",
// adresa pre fakturáciu aj doručovanie
"invoice_address" : {
"street" : "Nabrezna ulica",
"street_nr" : "2",
"city" : "Mikulas",
"post_code" : "03101",
"state" : "SR"
},
// uvedie sa len ak je odlišná od fakturačnej
"post_address" : {
"street" : "Nabrezna ulica 2",
"street_nr" : "2 2",
"city" : "Mikulas",
"post_code" : "03101",
"state" : "SR"
},
// kontaktné údaje
"contact" : {
"email" : "test@test.sk",
"phonenr" : "42199293923",
"title_pre" : "Ing.", // nepovinné
"name" : "Michal",
"surname" : "Kovac",
"title_post" : "Phd." // nepovinné
},
// predvolené hodnoty
"sender" : {
// textový odosielateľ
"text" : "TEST_API"
},
// uvediete len ak sa nevytvorila skupina do ktorej sa
// automaticky priradí nový zákazník a kde je uz callback
// definovaný pre celú skupinu spoločný
"callback_url" : "https://klientova.adresa/spracovanie.php"
}
}
```
** Výstupné dáta [JSON]
```json
{
"id" : "OK",
"data" : {
// pridelené ID Zákazníka
"cust_id" : "482",
// pridelené API KEY (integračný kľúč)
"apikey" : "API-KEY-RETAZEC"
},
"note" : "undef"
}
```

** Návratové hodnoty
Ostatné spoločné návratové hodnoty sú definované v časti 3.1

|	ID 			|	POPIS															|
|---------------|-------------------------------------------------------------------|
|DUPLICIT_ICO 	|	Registrácia nebola možná - IČO už je zaregistrované				|
|INVALID_EMAIL 	|	Nebol zadaný kontaktný email									|		
|NOT_RESELLER 	|	Registrujúci zákazník nie je Reseller - nemôže vykonať operáciu	|
|OTHER_ERROR 	|	Iná chyba - je nutná konzultácia								|

# 5. Callback

Uvádzame zoznam volaní (volanie adresuje náš systém na vaše URL, ktoré ste pre účely
callback zadali) aj s popisom JSON štruktúr.

## 5.1. Žiadosť o kredit [JSON]
```json
{
// pole stavov žiadostí o kredit
"credit_request": [
{
// id zakaznika (ak majú rovnaký callback URL viacerí zákazníci)
"cust_id" : 1234,
// 'ACCEPTED' - kredit pripísaný, 'REJECTED' - žiadosť zamietnutá
"state_id" : "STAV",
// variabilný symbol určený pri žiadosti o kredit
"vs" : "202020",
// požadovaný kredit - v prípade ACCEPTED aj reálne uhradená suma
"paid" : 123.50,
// pripísaný kredit navyše - bonus za výšku zaplatenej sumy
"bonus" : 13.50
}
]
}
```

## 5.2. Upozornenie na nízky stav kreditu [JSON]
```json
{
// pole upozornení na nízky kredit
"warning_low_credit": [
{
// id zákazníka
"cust_id": 13,
// momentálna hodnota kreditu v EUR
"credit_value": 7
}
]
}
```
## 5.3. Stav odoslania a doručenia SMS [JSON]
```json
{
// pole stavov SMS - odoslanie / doručenie
"sms_state": [
{
// ID správy
"msg_id": 1234567,
// Typ stavu - odosielanie / doručovanie
"state_type": "ODOSIELANIE",
// ID stavu
"state_id": "PREBIEHA_ODOSIELANIE",
// Jedná sa o permanentný stav ?
"permanent": "FALSE",
// Cena za správu v EUR
"price": 0.033,
// Počet sms na koľko bola správa rozdelená
"sms_count": 2
}
]
}
```
Popis rôznych stavov je uvedený v časti "4.2 Zistenie stavu doručenia".

## 5.4. Prijatie SMS [JSON]
```json
{
// pole prijatých SMS
"received_sms" : [
{
// tel. číslo odosielateľa
"sender_phonenr" : "421905000000",
// tel. číslo príjemcu (Virtuálne číslo - VLN)
"recipient_phonenr": "421901000002",
// ID pôvodnej správy (na ktorú prišla odpoveď)
// (nie je k dispozícii pre VLN)
"msg_id" : 6289473,
// ID odpovede
"response_id" : 172594,
// Typ odpovede - SMS (SMS správa) , CALL (telefonát)
"response_type" : "SMS",
// Text odpovede (pri type odpovede SMS)
"text" : "Testovacia SMS",
// Čas prijatia odpovede
"ts" : "09.12.2016 11:32"
}
]
}
```

## 5.5. Zmena stavu zákazníka [JSON]
```json
{
// pole zmien statov zákazníkov
"customer_state" : [
{
// ID zákazníka
"cust_id" : 453,
// Stav - môže nadobúdať hodnoty:
// ACCEPTED , REJECTED
"state" : "ACCEPTED"
}
]
}
```

# 6. Prílohy

## 6.1. GSM 3.38
Aby ste mohli použiť maximálny počet znakov v jednej SMS, text musí obsahovať znaky len zo
znakovej sady GSM 3.38:
• A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
• a b c d e f g h i j k l m n o p q r s t u v w x y z
• 0 1 2 3 4 5 6 7 8 9
• £ $ @ ! " # % & ' ( ) * + , - _ . / : ; < > = ?
• SP (medzera), CR, LF (nový riadok)
• pozor, tieto znaky zaberú v SMS priestor 2 znakov: ^ { } [ ] ~ | \ €
• a ďalšie menej používané znaky: GSM 3.38
Viac informácií o znakovej sade: GSM 3.38
38