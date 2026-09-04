/**
 * Encrypts plaintext/binary payloads for the provided recipient public keys.
 *
 * Builds an OpenPGP message object and returns an armored encrypted payload
 * using zip compression for smaller ciphertext output.
 *
 * @param {Object} messageOptions
 *   OpenPGP message input (`{ text }` or `{ binary }`).
 * @param {Array} publicKeys
 *   Parsed recipient public keys used for encryption.
 * @param {Object} openpgp
 *   OpenPGP.js library instance.
 *
 * @returns {Promise<string>}
 *   Armored encrypted message.
 */
export async function procEncrypt(messageOptions, publicKeys, openpgp) {
  const message = await openpgp.createMessage(messageOptions);
  return await openpgp.encrypt({
    encryptionKeys: publicKeys,
    message,
    format: 'armored',
    config: {
      preferredCompressionAlgorithm: openpgp.enums.compression.zip,
    },
  });
}
