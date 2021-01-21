from typing import NamedTuple, Optional, List

class Session(NamedTuple):
    """Session data"""
    session_id: int # should be crypto-random
    user_id: Optional[int] # from Scratch
    expiry: int # unix epoch time
    authing: Optional[str] # token-getter code
    nonce: Optional[str] # for logging in

class Nonce(NamedTuple):
    """Object containing nonce"""
    nonce: str # the nonce lol

class PartialApplication(NamedTuple):
    """Partial application data to be fetched in a list"""
    client_id: int
    app_name: Optional[str] # regardless of approval

class Application(NamedTuple):
    """Application data"""
    client_id: int
    client_secret: str
    app_name: Optional[str]
    approved: bool # whether app name is approved
    redirect_uris: List[str]

class Authing(NamedTuple):
    """Authorization process data"""
    code: str
    client_id: int
    redirect_uri: str
    scopes: List[str]
    state: Optional[str]
    expiry: Optional[int]