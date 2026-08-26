# Cybermon: Glitch Arena

Game ba màn dành cho gian hàng CLB An toàn thông tin. Game giữ lại kiểu chơi map/canvas của bản gốc: người chơi phải tự đi bằng phím mũi tên, chạm hotspot để gặp đối thủ, sau đó battle một đòn theo damage. Vị trí quái thường được xáo trộn ở mỗi lượt chơi; boss vẫn cố định.

## Cấu trúc ba màn

| Màn | Độ khó | Cách chơi | Điều kiện qua màn |
| --- | --- | --- | --- |
| 1 — Đồng Cỏ Khởi Động | Easy | Dùng ↑ ↓ ← → tìm 4 Wild Pikachu | Damage của bạn lớn hơn đối thủ trong cả 4 trận |
| 2 — Gym Khiên Hệ | Medium | Tự đi tới 1 trong 7 hotspot và đọc type, damage, debuff | Chọn đối thủ có type yếu hơn starter để thắng và qua màn; cùng hệ phải thắng damage nhưng chưa qua, còn hệ counter sẽ làm thua |
| 3 — Glitch Core | Hack | Đi tới NullByte Ω rồi chỉnh file Save/Load | Sửa trường `damage` lớn hơn `99.999` để vượt boss |

Sau mỗi màn có màn hình **Chúc mừng!** và nút sang màn tiếp theo. Nút này tự nhận focus để người chơi bấm **Enter/Space**. Khi thua ở bất kỳ màn nào, game hiển thị banner lý do và nút **Thử lại từ Màn 1**; chỉ sau khi xác nhận mới quay về bản đồ. Sau khi thắng màn 3, victory flag mới xuất hiện.

Save/Load luôn hiện diện trên thanh trên trong toàn bộ lượt chơi. File save giữ object Trainer cùng màn hiện tại và trạng thái chờ qua màn, nên nạp lại sẽ quay đúng màn đã lưu; trường `damage` vẫn là điểm deserialize có chủ đích để khai thác ở Màn 3.

Game không có bảng hint tích hợp; ban tổ chức sẽ hỗ trợ trực tiếp khi người chơi bị kẹt.

## Chạy nhanh

Yêu cầu: Docker Engine và Docker Compose plugin.

```bash
cp .env.example .env
nano .env
docker compose up --build -d
docker compose logs -f arena
```

Mở game tại `http://localhost:15001`. Nếu muốn các thiết bị cùng Wi-Fi truy cập, dùng IP LAN của máy tổ chức, ví dụ `http://192.168.1.20:15001`.

Sau khi container chạy, ban tổ chức có thể kiểm tra toàn bộ luồng bằng:

```bash
./smoke-test.sh http://127.0.0.1:15001
```

Dừng game:

```bash
docker compose down
```

Nếu Fedora đang bật firewalld:

```bash
sudo firewall-cmd --add-port=15001/tcp
sudo firewall-cmd --runtime-to-permanent
```

## Đổi mã chiến thắng

Sửa `.env` trước khi build/chạy:

```dotenv
PORT=15001
FINAL_FLAG=CLB_ATTT{MA_CHIEN_THANG_CUA_SU_KIEN}
```

Sau đó áp dụng lại:

```bash
docker compose up -d --build --force-recreate
```

Không đưa `.env` hoặc `ORGANIZER-SOLUTION.md` cho người chơi.

## Luồng dành cho người chơi

1. Nhập tên và chọn starter.
2. Dùng các phím mũi tên để đi đến từng biểu tượng trên map.
3. Khi gặp quái, so sánh damage; chỉ cần một đòn.
4. Ở Màn 2 có 7 đối thủ: type yếu hơn sẽ có debuff nhưng nếu thắng sẽ qua màn ngay; cùng hệ phải so damage và chỉ ghi nhận chiến thắng; hệ counter sẽ làm thua. Có thể **chạy** khỏi mọi trận để đổi đối thủ.
5. Nếu thua, đọc banner lý do rồi bấm **Enter/Space** ở nút thử lại. Màn 3 vẫn phải đi tới boss; khi đã sẵn sàng, tải save, sửa trường `damage` lớn hơn `99.999` rồi nạp lại.
6. Sau victory, đưa flag cho thành viên CLB.

## Thiết kế an toàn cho hội trại

Lỗ hổng ở Màn 3 là có chủ đích nhưng đã được giới hạn:

- `unserialize()` chỉ cho phép class `Trainer`.
- Không có magic method, lệnh hệ thống, truy cập file, database hoặc network trong object.
- Khi load save ở Màn 1/2, damage được kẹp theo số trận đã thắng; chỉ Màn 3 mở một ngưỡng damage hữu hạn cho puzzle.
- File upload tối đa 4 KB ở application và 8 KB ở PHP.
- Container chạy filesystem read-only; chỉ `/tmp` dùng cho session/upload tạm.
- PHP network primitives và các hàm thực thi process bị tắt.

Vẫn nên chỉ chạy trên máy riêng cho sự kiện, đặt trong VLAN/Wi-Fi khách nếu có, không mount Docker socket, không mount thư mục host và không public port này ra Internet.

## Reset toàn bộ session giữa hai ca

Session nằm trong tmpfs của container. Khởi động lại sẽ xóa toàn bộ lượt chơi:

```bash
docker compose restart arena
```

## File quan trọng

```text
app/Trainer.php              Trainer và stats damage
app/bootstrap.php            Session, encounter map và state helpers
public/api/game.php          Di chuyển logic server-side, battle, progression
public/api/save-load.php     Save/Load và điểm deserialize có chủ đích
public/api/source.php        Endpoint cũ được vô hiệu hóa
public/assets/game.js        Keyboard movement, map/canvas, battle UI
public/assets/world.css      Giao diện map/battle/responsive
ORGANIZER-SOLUTION.md        Lời giải, kiểm thử và hướng dẫn trợ giúp
```
