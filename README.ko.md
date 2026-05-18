🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# PolyPay 워드프레스 결제 플러그인

[PolyPay](https://polypay.ai)를 통해 모든 WordPress 사이트에서 암호화폐 결제(USDT, USDC 등)를 받을 수 있습니다. 단독 숏코드 모드와 WooCommerce 게이트웨이 모드를 모두 지원합니다.

지원 네트워크: **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## 주요 기능

- WooCommerce 없이 사용 가능
- 숏코드 `[polypay_button]` 지원
- WooCommerce 결제 게이트웨이 자동 연동
- 설정 페이지: `Settings -> PolyPay`
- 결제 기록 테이블 내장

## 설치

1. `polypay` 폴더를 `/wp-content/plugins/`에 업로드
2. WordPress 관리자에서 플러그인 활성화
3. `Settings -> PolyPay`에서 API Key 입력

## 사용 예시

```text
[polypay_button amount="99.99"]
```

전체 파라미터 예시:

```text
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## 링크

- 콘솔: https://polypay.ai
- 문서: https://polypay.ai/docs
