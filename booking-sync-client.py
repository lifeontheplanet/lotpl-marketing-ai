#!/usr/bin/env python3
"""Encrypt reviewed LOTPL ChatGPT booking deltas; output ciphertext only for GitHub."""
import argparse, base64, json, os, re
from cryptography.hazmat.primitives.asymmetric import x25519
from cryptography.hazmat.primitives import serialization, hashes
from cryptography.hazmat.primitives.kdf.hkdf import HKDF
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

def b64(x): return base64.b64encode(x).decode("ascii")

def encrypt(site_public, payload):
    raw=base64.b64decode(site_public, validate=True)
    if len(raw)!=32: raise ValueError("invalid public key")
    key=x25519.X25519PublicKey.from_public_bytes(raw)
    eph=x25519.X25519PrivateKey.generate()
    salt,iv=os.urandom(16),os.urandom(12)
    aes_key=HKDF(algorithm=hashes.SHA256(),length=32,salt=salt,
                 info=b"LOTPL booking board transport v1").derive(eph.exchange(key))
    plain=json.dumps(payload,ensure_ascii=False,separators=(",",":")).encode()
    c=AESGCM(aes_key).encrypt(iv,plain,b"LOTPL.BOOKING.BOARD.v1")
    ephemeral_pub=eph.public_key().public_bytes(encoding=serialization.Encoding.Raw,format=serialization.PublicFormat.Raw)
    return {"schema":"lotpl.booking.ciphertext.v1","key_id":"v1","ephemeral_pub":b64(ephemeral_pub),
            "salt":b64(salt),"iv":b64(iv),"ciphertext":b64(c)}

def main():
    p=argparse.ArgumentParser()
    p.add_argument("--public-key",required=True)
    p.add_argument("--input",required=True,help="PRIVATE local JSON reviewed cases")
    p.add_argument("--base",required=True,help="current encrypted map")
    p.add_argument("--output",required=True,help="new encrypted map for GitHub")
    a=p.parse_args()
    with open(a.input,encoding="utf-8") as f: source=json.load(f)
    with open(a.base,encoding="utf-8") as f: result=json.load(f)
    if source.get("schema")!="lotpl.chatgpt_booking_ledger.v1" or not isinstance(source.get("cases"),list):
        raise ValueError("invalid ledger")
    if result.get("schema")!="lotpl.booking.encrypted_records.v1" or not isinstance(result.get("records"),dict):
        raise ValueError("invalid encrypted map")
    seen=set()
    for case in source["cases"]:
        key=case.get("case_key","") if isinstance(case,dict) else ""
        if not isinstance(key,str) or not re.fullmatch(r"chat-[a-z0-9]{12,59}",key) or key in seen:
            raise ValueError("bad or duplicate case key")
        seen.add(key)
        result["records"][key]=encrypt(a.public_key,{"schema":"lotpl.chatgpt_booking_ledger.v1","cases":[case]})
    if len(result["records"])>300: raise ValueError("too many records")
    output=json.dumps(result,ensure_ascii=False,separators=(",",":"))
    for case in source["cases"]:
        for field in ("guest_name","email","tour_name","note"):
            val=case.get(field,"")
            if isinstance(val,str) and len(val)>4 and val in output:
                raise ValueError("plaintext in output")
    with open(a.output,"w",encoding="utf-8") as f:f.write(output+"\n")
    print("LOTPL_CIPHERTEXT_ONLY=PASS; RECORDS="+str(len(result["records"])))

if __name__=="__main__":main()
