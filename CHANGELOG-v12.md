# v12 — Toàn bộ save bọc Base64

- Save xuất Base64 của toàn bộ PHP serialized Trainer; Load giải mã trước unserialize.
- Bắt buộc Base64 hợp lệ, hỗ trợ file có xuống dòng; từ chối object thô và dữ liệu quá lớn.
- Giữ ký checkpoint, lỗ hổng technique, gameplay, font, title và flag.
- Upload tối đa 96 KB; payload sau giải mã tối đa 64 KB; post_max_size 128K.
- Cập nhật smoke test và lời giải theo luồng decode → sửa technique → encode → Load → Thủ.

Kiểm chứng v12: smoke test cả ba starter qua HTTP tới PHP 8.3 WebAssembly CGI; gồm lưu/nạp giữa trận, pending stage, giải mã/sửa/mã hóa kỹ năng, boss thắng/thua, checkpoint giả, Base64 lỗi, object thô, upload quá lớn và Base64 xuống dòng. Chưa chạy Apache/Docker thực tế.
