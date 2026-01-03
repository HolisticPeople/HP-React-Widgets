const https = require('https');

const data = JSON.stringify({
  jsonrpc: "2.0",
  id: 1,
  method: "tools/call",
  params: {
    name: "hp-funnels-seo-audit",
    arguments: {
      slug: "liver-detox-protocol"
    }
  }
});

const options = {
  hostname: 'holisticpeople.com',
  port: 443,
  path: '/wp-json/woocommerce/mcp',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-MCP-API-Key': 'ck_607755c07ede34effa0a599780931fd5b750eebe:cs_4ed7859da0385dea30cc27f0587ea0df35c7fc8c',
    'Content-Length': data.length
  }
};

const req = https.request(options, (res) => {
  let body = '';
  res.on('data', (d) => { body += d; });
  res.on('end', () => {
    console.log('STATUS:', res.statusCode);
    console.log('HEADERS:', JSON.stringify(res.headers, null, 2));
    console.log('BODY:', body);
  });
});

req.on('error', (e) => {
  console.error(e);
});

req.write(data);
req.end();











const data = JSON.stringify({
  jsonrpc: "2.0",
  id: 1,
  method: "tools/call",
  params: {
    name: "hp-funnels-seo-audit",
    arguments: {
      slug: "liver-detox-protocol"
    }
  }
});

const options = {
  hostname: 'holisticpeople.com',
  port: 443,
  path: '/wp-json/woocommerce/mcp',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-MCP-API-Key': 'ck_607755c07ede34effa0a599780931fd5b750eebe:cs_4ed7859da0385dea30cc27f0587ea0df35c7fc8c',
    'Content-Length': data.length
  }
};

const req = https.request(options, (res) => {
  let body = '';
  res.on('data', (d) => { body += d; });
  res.on('end', () => {
    console.log('STATUS:', res.statusCode);
    console.log('HEADERS:', JSON.stringify(res.headers, null, 2));
    console.log('BODY:', body);
  });
});

req.on('error', (e) => {
  console.error(e);
});

req.write(data);
req.end();











