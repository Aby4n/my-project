import pygame
import random

# Inisialisasi
pygame.init()

# Ukuran layar
WIDTH = 500
HEIGHT = 600
screen = pygame.display.set_mode((WIDTH, HEIGHT))
pygame.display.set_caption("Plane Shooter 2D")

# Warna
WHITE = (255, 255, 255)
RED = (255, 0, 0)

# Player
player_width = 50
player_height = 50
player_x = WIDTH // 2
player_y = HEIGHT - 70
player_speed = 5

# Bullet
bullets = []
bullet_speed = 7

# Enemy
enemies = []
enemy_speed = 3

# Score
score = 0
font = pygame.font.SysFont(None, 36)

# Clock
clock = pygame.time.Clock()

running = True
while running:
    clock.tick(60)
    screen.fill((0, 0, 0))

    # Event
    for event in pygame.event.get():
        if event.type == pygame.QUIT:
            running = False

    # Kontrol
    keys = pygame.key.get_pressed()
    if keys[pygame.K_LEFT] and player_x > 0:
        player_x -= player_speed
    if keys[pygame.K_RIGHT] and player_x < WIDTH - player_width:
        player_x += player_speed
    if keys[pygame.K_SPACE]:
        bullets.append([player_x + player_width // 2, player_y])

    # Update bullets
    for bullet in bullets:
        bullet[1] -= bullet_speed
    bullets = [b for b in bullets if b[1] > 0]

    # Spawn enemy
    if random.randint(1, 30) == 1:
        enemies.append([random.randint(0, WIDTH - 40), 0])

    # Update enemy
    for enemy in enemies:
        enemy[1] += enemy_speed

    # Collision
    for enemy in enemies[:]:
        for bullet in bullets[:]:
            if (enemy[0] < bullet[0] < enemy[0] + 40 and
                enemy[1] < bullet[1] < enemy[1] + 40):
                enemies.remove(enemy)
                bullets.remove(bullet)
                score += 1
                break

    # Draw player
    pygame.draw.rect(screen, WHITE, (player_x, player_y, player_width, player_height))

    # Draw bullets
    for bullet in bullets:
        pygame.draw.rect(screen, RED, (bullet[0], bullet[1], 5, 10))

    # Draw enemies
    for enemy in enemies:
        pygame.draw.rect(screen, (0, 255, 0), (enemy[0], enemy[1], 40, 40))

    # Tampilkan score
    text = font.render(f"Score: {score}", True, WHITE)
    screen.blit(text, (10, 10))

    pygame.display.update()

pygame.quit()