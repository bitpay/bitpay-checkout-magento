var mysql = require('mysql');
var format = require('string-template');
var query = 'select * from bitpay_invoices where quote_id=(select MAX(quote_id) from bitpay_invoices)';
var spawn = require('child_process').spawn;
var config = require('./config.json');
var data = {};
var connection = mysql.createConnection(config.mysql);

function postIpn() {
  connection.connect();
  connection.query(query, processRows);
}

function send(curl_args) {
  var curl = spawn('curl', curl_args);
  var stderr;
  curl.stdout.on('data', function(data) {
    console.log(data.toString());
  });
  curl.stderr.on('data', function(data) {
    stderr = data;
  });
  curl.on('close', function(code) {
    if (code === 0) {
      console.log('curl exited successfully');
    } else {
      console.log('curl exited with an error: ' + stderr);
    }
  });
}

function processRows(err, rows, fields) {
  if (err) {
    throw err;
  }
  var curl_args = [
    '-X', 'POST', '-H',
    'Content-Type: application/json',
    '-H', "Content-Length: {length}",
    '-H', 'Connection: close',
    '-H', 'Accept: application/json',
    '-d', '',
    config.host ];
  var convertedKeys = convertNames(fields);
  var timeRegExp = new RegExp(/.*Time$/);
  for (var i=0; i<convertedKeys.length; i++) {
    var rowValue = rows[0][fields[i].name];
    if (convertedKeys[i] === 'status') {
      data[convertedKeys[i]] = config.status;
    } else if (convertedKeys[i].match(timeRegExp)) {
      data[convertedKeys[i]] = rowValue * 1000;
    }
    else {
      data[convertedKeys[i]] = rowValue;
    }
  }
  data.buyerFields = {};
  data.url = 'https://test.bitpay.com:443/invoice?id=' + rows[0].id;
  data.posData = '{\"orderId\":\"' + rows[0].order_id.toString() + '\"}';
  data.btcPaid = data.btcPrice;
  data.btcDue = '0.000000';
  var jsonPayload = JSON.stringify(data);
  curl_args[5] = format(curl_args[5], {length: jsonPayload.length});
  curl_args[11] = jsonPayload;
  connection.end();
  send(curl_args);
}

function convertNames(names) {
  var ret = [];
  if (!names || typeof names !== 'object' || names.length === 0) {
    return ret;
  }

  for (var j = 0; j < names.length; j++) {
    var name = names[j].name;
    var converted = name[0];
    var i = 1;
    while (i < (name.length - 1)) {
      if (name[i] === '_') {
        converted += name[i+1].toUpperCase();
        i = i+2;
      } else {
        converted += name[i];
        i++;
      }
    }
    converted += name[name.length-1];
    ret.push(converted);
  }

  return ret;
}

postIpn();
