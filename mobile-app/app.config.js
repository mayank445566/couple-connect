// Extends app.json at build time.
// Push notifications need Firebase's google-services.json. Either put it next to this file
// (it is git-ignored) or upload it to EAS as a file environment variable named GOOGLE_SERVICES_JSON.
const fs = require('fs');
const path = require('path');

module.exports = ({ config }) => {
  const file = process.env.GOOGLE_SERVICES_JSON || (fs.existsSync(path.join(__dirname, 'google-services.json')) ? './google-services.json' : null);
  if (file) config.android = { ...config.android, googleServicesFile: file };
  return config;
};
