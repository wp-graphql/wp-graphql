async function hashWithWebCrypto(input) {
  const subtle = globalThis.crypto?.subtle
  if (!subtle) return null
  const bytes = new TextEncoder().encode(input)
  const buf = await subtle.digest("SHA-256", bytes)
  return bufferToHex(new Uint8Array(buf))
}

async function hashWithNodeCrypto(input) {
  const { createHash } = await import("node:crypto")
  return createHash("sha256").update(input, "utf8").digest("hex")
}

function bufferToHex(bytes) {
  const out = new Array(bytes.length)
  for (let i = 0; i < bytes.length; i++) {
    out[i] = bytes[i].toString(16).padStart(2, "0")
  }
  return out.join("")
}

export async function sha256(input) {
  if (typeof input !== "string") {
    throw new TypeError("sha256: input must be a string")
  }
  const fromWebCrypto = await hashWithWebCrypto(input)
  if (fromWebCrypto) return fromWebCrypto
  return hashWithNodeCrypto(input)
}
