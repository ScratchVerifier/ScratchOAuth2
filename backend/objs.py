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
    app_name: str # regardless of approval

class Application(NamedTuple):
    """Application data"""
    client_id: int
    client_secret: str
    app_name: str
    name_approved: bool
    redirect_uris: List[str]