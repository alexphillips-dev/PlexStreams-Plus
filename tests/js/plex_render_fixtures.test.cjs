const assert = require('assert');
const fs = require('fs');
const path = require('path');

global._ = function (value) {
  return value;
};

const plexJsPath = path.resolve(__dirname, '../../src/plexstreamsplus/usr/local/emhttp/plugins/plexstreamsplus/js/plex.js');
const fixturePath = path.resolve(__dirname, '../fixtures/stream_video_transcode.json');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const plex = require(plexJsPath);

function run() {
  assert.strictEqual(plex.streamAudioValue(fixture), 'Copy');
  assert.strictEqual(plex.streamVideoValue(fixture), 'Transcode (1080p (H.264))');
  assert.strictEqual(
    plex.streamEpisodeMeta({ type: 'video', title: 'Animal Control - Season 4 - Roosters and Moles (2026)' }),
    'S4 - Roosters and Moles'
  );

  const signatureBaseline = plex.streamStaticSignature(fixture);
  const signatureProgressOnly = plex.streamStaticSignature({
    ...fixture,
    percentPlayed: fixture.percentPlayed + 8,
    currentPositionSeconds: fixture.currentPositionSeconds + 5
  });
  const signatureDifferentTitle = plex.streamStaticSignature({
    ...fixture,
    title: 'Different Title'
  });

  assert.strictEqual(signatureBaseline, signatureProgressOnly);
  assert.notStrictEqual(signatureBaseline, signatureDifferentTitle);

  const escapedCard = plex.buildFullStreamCard({
    ...fixture,
    title: '<img src=x onerror=alert(1)>'
  });
  assert(escapedCard.includes('&lt;img src=x onerror=alert(1)&gt;'));
  assert(!escapedCard.includes('<img src=x onerror=alert(1)>'));

  const parsed = plex.plexStreamsPlusParseCustomServerEntries('192.168.1.2:32400, plex.local:32400, bad%%host');
  assert.deepStrictEqual(parsed.invalidEntries, ['bad%%host']);

  plex.plexStreamsPlusResetPollState('fixture-test');
  assert.strictEqual(plex.plexStreamsPlusNextPollDelay('fixture-test'), 5000);
  plex.plexStreamsPlusMarkPoll('fixture-test', 0, false);
  assert.strictEqual(plex.plexStreamsPlusNextPollDelay('fixture-test'), 10000);
  plex.plexStreamsPlusMarkPoll('fixture-test', 4, false);
  assert.strictEqual(plex.plexStreamsPlusNextPollDelay('fixture-test'), 5000);
  plex.plexStreamsPlusMarkPoll('fixture-test', 0, true);
  assert.strictEqual(plex.plexStreamsPlusNextPollDelay('fixture-test'), 10000);
}

run();
console.log('Fixture tests passed: plex_render_fixtures.test.cjs');
