# Cybermon: Pokemon Arena

Bản Tactical / Endurance / Paradox (v12, font pixel rõ nét từ v11), dành cho gian hàng SRC. Giữ map pixel, nhạc, khám phá bằng phím mũi tên và giao diện hoài niệm. Các trận đấu giờ diễn ra theo lượt.

## Ba màn chơi

| Màn | Luật mới | Điều kiện qua màn |
| --- | --- | --- |
| Đồng Cỏ Khởi Động | 6 Pikachu tăng dần HP/damage; đọc ý đồ và chọn Đánh / Thủ / Phá thủ; 3 bình hồi phục | Hạ cả 6 |
| Counter | 2 hộ vệ và 5 đấu sĩ phụ; hộ vệ đổi hệ Fire → Water → Grass và tăng 4 damage sau mỗi lượt; 4 bình khởi đầu | Hạ cả 2 hộ vệ |
| BOSS | NullByte ép HP và damage về 1 khi gặp và trước mỗi đòn | Tìm cách phá luật bằng save; chi tiết chỉ trong tài liệu ban tổ chức |

Đánh thắng Phá thủ; Phá thủ thắng Thủ; Thủ thắng Đánh. Đúng thế gây nhiều damage và nhận ít sát thương; Thủ đúng thế chặn hoàn toàn. Hòa thế vẫn trao đổi sát thương. Bất lợi thế gây ít damage và nhận đòn nặng.

HP giữ nguyên giữa các trận; thắng được +3 damage. Hồi phục +45 HP (tối đa 100) tốn một lượt. Nếu đối thủ đang Thủ thì dùng bình không bị đánh trả. Đấu sĩ phụ Màn 2 thưởng thêm 1 bình khi bị hạ. Qua màn hồi đầy HP và cấp số bình của màn mới. Bỏ chạy không mất lượt hay HP, nhưng lần gặp lại đối thủ sẽ đầy HP và quay về trạng thái ban đầu.

Màn 2 có lợi hệ nhân damage ×1,5, bất lợi ×0,65; sát thương nhận thay đổi ngược lại. Không có auto-lose chỉ vì gặp counter. Quái có vị trí ngẫu nhiên mỗi lượt chơi, boss cố định. Thua bất kỳ màn nào đều có banner và nút xác nhận chơi lại từ Màn 1.

## Điều khiển

- Trên map: ↑ ↓ ← → di chuyển, chạm đối thủ để vào battle.
- Trong battle: ↑/← và ↓/→ chuyển qua 5 nút; Enter hoặc Space xác nhận. Chuột vẫn dùng được.
- Nút chuyển màn / chơi lại: Enter hoặc Space.
- Save/Load và nhạc ở thanh đầu trang. Save lưu màn, quái đã hạ, HP, bình, ý đồ và trận đang đánh. Vị trí đứng trên map được đặt về điểm xuất phát khi load.

## Chạy bằng Docker

```bash
cp .env.example .env
```

Đặt `SAVE_SECRET` trong `.env` bằng một chuỗi ngẫu nhiên dài ít nhất 32 ký tự. Có thể tạo bằng:

```bash
python3 -c 'import secrets; print(secrets.token_hex(32))'
```

Giữ khóa này ổn định để save dùng được sau restart/redeploy. Nếu để trống, game dùng khóa tạm trong `/tmp`; khi container bị tạo lại, save cũ có thể không còn hợp lệ. Nhiều instance phải dùng cùng khóa. Không đưa `.env` lên repo public.

```bash
docker compose up --build -d
./smoke-test.sh http://localhost:15001
```

Smoke test cần Python 3 trên máy chạy lệnh. Dùng session riêng và kiểm tra cả ba starter, trận nhiều lượt, save giữa battle, chuyển màn, boss, sửa checkpoint và puzzle mới.

Flag và title tùy biến được giữ từ bản trước. `FINAL_FLAG` trong cấu hình triển khai vẫn là nguồn flag. Bản này thay đổi luật và định dạng save: session cũ tự bắt đầu lại từ Màn 1; save các phiên bản trước v10 không tương thích.

## Kiểm tra trước hội trại

Chạy smoke test trên deployment Apache/Docker thật, sau đó cho người chơi thử để cân bằng thời lượng. Không thay key giữa sự kiện. Chỉ thư mục `public` được serve; `ORGANIZER-SOLUTION.md` và smoke test chứa lời giải, dành riêng cho ban tổ chức.

## Định dạng save v12

Toàn bộ `.sav` được bọc Base64, bao gồm object Trainer, kỹ năng và checkpoint đã ký. Game tự xử lý khi Save/Load; không thêm hint giải mã vào giao diện người chơi. Giới hạn upload 96 KB; dữ liệu sau giải mã tối đa 64 KB. Lời giải cập nhật nằm trong tài liệu ban tổ chức.

Session và checkpoint v10/v11 vẫn giữ nguyên phiên bản nội bộ; không reset tiến trình chỉ vì cập nhật này. File save v10/v11 dạng object thô cần bọc Base64 toàn bộ trước khi Load; vẫn phải dùng cùng SAVE_SECRET. Không thay đổi khóa ký hay cơ chế phản đòn.

## Cập nhật repo đã clone

Dùng thư mục tạo bằng `git clone`, thư mục này tự có lịch sử Git; không cần `git init` cho mỗi bản cập nhật:

```bash
git pull --ff-only
```

Sau đó build lại game bằng `docker compose up --build -d` trong thư mục repo.
